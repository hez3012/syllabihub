// "Sage" floating chat widget (layouts/app.blade.php, @auth
// only). Talks to POST /api/chat (App\Http\Controllers\ChatbotController)
// — that endpoint itself is stateless (no chat-log table, per
// ChatbotController's docblock), so the conversation only exists here on
// the client. This is a traditional multi-page Blade app, not an SPA, so
// a plain in-memory array would reset on every navigation — that was a
// real bug (Rico, 2026-08-13): moving to another page, or even just
// closing the panel with the X, felt like it "lost" the conversation.
// Persisted to sessionStorage instead: survives page navigation and the
// X button (that only hides the panel, see closePanel()), but still
// clears on its own when the browser tab actually closes — the right
// lifetime for "this conversation", not "forever on this computer".
document.addEventListener('DOMContentLoaded', function () {
    const widget = document.getElementById('chatbot-widget');
    if (!widget) {
        return; // guest page, or widget markup not present
    }

    const toggleBtn = document.getElementById('chatbot-toggle');
    const closeBtn = document.getElementById('chatbot-close');
    const newChatBtn = document.getElementById('chatbot-new-chat');
    const languageSelect = document.getElementById('chatbot-language');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');
    // Captured before restoreTranscript() can touch the DOM, so "New
    // chat" always has the original greeting to bring back, exactly as
    // Rico wrote it in the blade markup — not hardcoded a second time
    // here where it could drift out of sync with that copy.
    const greetingHtml = messages.innerHTML;
    const toggleIcon = toggleBtn.querySelector('i');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Server caps history at 20 entries / 2000 chars each (ChatbotController)
    // — mirrored here so a long conversation trims client-side instead of
    // eventually failing validation.
    const MAX_HISTORY = 20;
    const MAX_HISTORY_CHARS = 2000;

    // Scoped to the logged-in user, not just the browser tab — real
    // security bug (Rico, 2026-08-13): sessionStorage is shared by the
    // whole tab/origin, so a flat 'sage-chat-transcript' key kept
    // showing account A's conversation after logging out and into
    // account B in the same tab, including anything account A had asked
    // Sage about. Falls back to 'guest' only as a defensive default —
    // this script bails out above on any page without #chatbot-widget,
    // which itself only ever renders inside @auth, so the meta tag
    // should always be present in practice.
    const userId = document.querySelector('meta[name="auth-user-id"]')?.content || 'guest';
    const STORAGE_KEY = `sage-chat-transcript-${userId}`;

    // Reply-language preference (2026-08-13, per Rico/supervisor) — same
    // per-user sessionStorage scoping/lifetime as the transcript above,
    // for the same reason (a flat key would leak across accounts on a
    // shared computer). Independent of "New chat" — clearing the
    // conversation shouldn't silently reset a deliberate language choice.
    const LANGUAGE_STORAGE_KEY = `sage-chat-language-${userId}`;
    const VALID_LANGUAGES = ['english', 'tagalog', 'taglish'];

    // The one source of truth for the conversation — each entry is
    // {role, content, sources?, showLinks?}. `sources`/`showLinks` are
    // only ever set on assistant entries, and only used to replay the
    // result-links row exactly as it was first shown (see
    // restoreTranscript()); the `history` array the API actually
    // receives is always just {role, content} pairs derived from this.
    let transcript = loadTranscript();
    let sending = false;
    let currentLanguage = loadLanguage();
    languageSelect.value = currentLanguage;

    languageSelect.addEventListener('change', function () {
        currentLanguage = languageSelect.value;
        saveLanguage(currentLanguage);
    });

    function loadTranscript() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);

            return raw ? JSON.parse(raw) : [];
        } catch (error) {
            return []; // corrupted/blocked storage — start clean rather than break the widget
        }
    }

    function saveTranscript() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(transcript));
        } catch (error) {
            // Storage full/blocked (e.g. private browsing) — conversation
            // still works for this page view, it just won't carry over.
        }
    }

    /** Defaults to English (per Rico) on first visit, an invalid/corrupted stored value, or blocked storage. */
    function loadLanguage() {
        try {
            const stored = sessionStorage.getItem(LANGUAGE_STORAGE_KEY);

            return VALID_LANGUAGES.includes(stored) ? stored : 'english';
        } catch (error) {
            return 'english';
        }
    }

    function saveLanguage(language) {
        try {
            sessionStorage.setItem(LANGUAGE_STORAGE_KEY, language);
        } catch (error) {
            // Storage full/blocked — the selection still works for this page view.
        }
    }

    function openPanel() {
        widget.classList.add('is-open');
        toggleIcon.className = 'bi bi-x-lg';
        toggleBtn.setAttribute('aria-label', 'Close Sage');
        setTimeout(() => input.focus(), 150);
    }

    function closePanel() {
        widget.classList.remove('is-open');
        toggleIcon.className = 'bi bi-chat-dots-fill';
        toggleBtn.setAttribute('aria-label', 'Open Sage');
    }

    toggleBtn.addEventListener('click', function () {
        if (widget.classList.contains('is-open')) {
            closePanel();
        } else {
            openPanel();
        }
    });

    closeBtn.addEventListener('click', closePanel);

    /** "New chat" — Rico, 2026-08-13: there was no way to actually clear
     *  a conversation short of closing the browser tab (sessionStorage
     *  keeps it forever otherwise). Wipes the transcript, the stored
     *  copy, and the visible messages, then brings back the original
     *  greeting so the panel looks exactly like a first-ever visit. */
    function resetConversation() {
        transcript = [];
        saveTranscript();
        messages.innerHTML = greetingHtml;
    }

    /** Confirms before wiping anything — Rico, 2026-08-13: the button
     *  had no confirmation at all, so one stray click permanently lost
     *  the conversation with no way back. A plain native confirm() is
     *  deliberately used over a custom modal — this project's Bootstrap-
     *  only, test-stub-level UI (CLAUDE.md §14) doesn't need more than
     *  that to actually solve the problem, and it needs no extra markup. */
    newChatBtn.addEventListener('click', function () {
        if (confirm('Start a new chat? This will clear your current conversation with Sage.')) {
            resetConversation();
        }
    });

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    /** User bubbles always use textContent — the user's own input is
     *  untrusted and has no reason to contain markdown anyway, so that
     *  stays the XSS guard it always was. Assistant bubbles go through
     *  formatAssistantHtml() instead (see its docblock for why that's
     *  still safe against LLM output). */
    function addMessageBubble(role, text) {
        const row = document.createElement('div');
        row.className = `chatbot-msg chatbot-msg-${role} chatbot-msg-in`;

        const bubble = document.createElement('div');
        bubble.className = 'chatbot-bubble';

        if (role === 'assistant') {
            bubble.innerHTML = formatAssistantHtml(text);
        } else {
            bubble.textContent = text;
        }

        row.appendChild(bubble);
        messages.appendChild(row);
        scrollToBottom();

        return row;
    }

    /**
     * Groq's replies naturally come back with light markdown —
     * "**term**" for emphasis, "- item" lines for lists (see
     * ChatbotService::SYSTEM_PROMPT, which never tells it not to). Left
     * as plain textContent, that shows up as literal asterisks and
     * dashes with no spacing — not a rendering choice, a bug (Rico,
     * 2026-08-13). This turns exactly those two constructs into real
     * <strong>/<ul><li> markup and nothing else.
     *
     * Still safe against arbitrary HTML injection despite being LLM
     * output: escaping happens FIRST, via the browser's own textContent
     * -> innerHTML round-trip (same trick jQuery's $.text() uses), so
     * any literal "<", "&", etc. in the reply is neutralized before the
     * two regexes below ever run. Those regexes then only ever
     * *introduce* the handful of fixed tags written directly in this
     * function — there's no path from Groq's text to an arbitrary tag
     * or attribute landing in the DOM.
     */
    function formatAssistantHtml(text) {
        const escaper = document.createElement('div');
        escaper.textContent = text;
        const escaped = escaper.innerHTML.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        const html = [];
        let inList = false;

        escaped.split('\n').forEach(function (line) {
            const bullet = line.match(/^\s*[-*]\s+(.+)$/);

            if (bullet) {
                if (!inList) {
                    html.push('<ul class="chatbot-bubble-list">');
                    inList = true;
                }
                html.push(`<li>${bullet[1]}</li>`);
                return;
            }

            if (inList) {
                html.push('</ul>');
                inList = false;
            }

            html.push(line === '' ? '<br>' : `<div>${line}</div>`);
        });

        if (inList) {
            html.push('</ul>');
        }

        return html.join('');
    }

    function addResultLinks(row, results) {
        if (!results || results.length === 0) {
            return;
        }

        const list = document.createElement('div');
        list.className = 'chatbot-results';

        results.forEach(function (result) {
            const files = result.syllabus_files || [];

            if (files.length === 0) {
                // No file uploaded yet — link to the course page instead
                // of a download, clearly marked so it's not mistaken for one.
                list.appendChild(buildResultLink(
                    `/courses/${result.course_id}`,
                    `${result.course_code} — ${result.title}`,
                    'bi bi-file-earmark-x text-muted'
                ));
                return;
            }

            // One link per file — a course with both a PDF and a DOCX
            // syllabus gets two separate, individually downloadable rows.
            files.forEach(function (file) {
                list.appendChild(buildResultLink(
                    `/syllabi/${file.id}/download`,
                    `${result.course_code} — ${result.title} (${file.file_type.toUpperCase()})`,
                    'bi bi-download'
                ));
            });
        });

        row.appendChild(list);
        scrollToBottom();
    }

    /** Clicking this downloads immediately — SyllabusController::download()
     *  serves the file with Content-Disposition: attachment, so a plain
     *  <a href> is all a direct download needs, no extra JS. */
    function buildResultLink(href, label, iconClass) {
        const link = document.createElement('a');
        link.className = 'chatbot-result-link';
        link.href = href;

        const labelEl = document.createElement('span');
        labelEl.textContent = label;

        const icon = document.createElement('i');
        icon.className = iconClass;

        link.appendChild(labelEl);
        link.appendChild(icon);

        return link;
    }

    function addTypingIndicator() {
        const row = document.createElement('div');
        row.className = 'chatbot-msg chatbot-msg-assistant chatbot-msg-in';
        row.id = 'chatbot-typing';

        const bubble = document.createElement('div');
        bubble.className = 'chatbot-bubble';
        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('span');
            dot.className = 'chatbot-typing-dot';
            bubble.appendChild(dot);
        }

        row.appendChild(bubble);
        messages.appendChild(row);
        scrollToBottom();
    }

    function removeTypingIndicator() {
        document.getElementById('chatbot-typing')?.remove();
    }

    /** Adds one turn to `transcript`, trims it the same way the old
     *  `history` array used to, and persists it — every call is a point
     *  where the conversation could next be interrupted by a navigation,
     *  so it has to be saved right here, not batched for later. */
    function pushTranscript(entry) {
        transcript.push({
            ...entry,
            content: entry.content.length > MAX_HISTORY_CHARS ? entry.content.slice(0, MAX_HISTORY_CHARS) : entry.content,
        });

        if (transcript.length > MAX_HISTORY) {
            transcript = transcript.slice(-MAX_HISTORY);
        }

        saveTranscript();
    }

    /** {role, content} pairs only — what the API actually expects; strips
     *  the sources/showLinks bookkeeping pushTranscript() also carries. */
    function apiHistory() {
        return transcript.map(({ role, content }) => ({ role, content }));
    }

    /**
     * Sage only shows the download/course-page links when the message
     * actually asked for one — per Rico, 2026-08-13: having them appear
     * under every single reply regardless of what was asked was
     * cluttering the chat ("ang sakit sa mata"). The answer text alone
     * still covers most questions; this is deliberately just the
     * explicit-request signal, not query_type, so it stays a pure
     * "did the user ask for this" read rather than a guess about intent.
     */
    function messageWantsLinks(message) {
        return /\b(link|links|download|pdf|docx|file|files|send|ipadala|padala|ibigay|bigay|kunin|buksan|open|share|attach)\b/i.test(message);
    }

    /** Replays a saved conversation into the DOM on page load — same
     *  rendering path as a live reply (addMessageBubble/addResultLinks),
     *  so a restored thread looks identical to how it was first shown,
     *  links included only where they were originally shown. Drops the
     *  static "Hi, I'm Sage!" greeting from the markup first when there's
     *  an actual conversation to restore, so continuing it on a new page
     *  doesn't look like Sage is re-introducing itself mid-conversation. */
    function restoreTranscript() {
        if (transcript.length === 0) {
            return;
        }

        messages.innerHTML = '';

        transcript.forEach(function (entry) {
            const row = addMessageBubble(entry.role, entry.content);

            if (entry.role === 'assistant' && entry.showLinks && entry.sources) {
                addResultLinks(row, entry.sources);
            }
        });
    }

    restoreTranscript();

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const message = input.value.trim();
        if (!message || sending) {
            return;
        }

        sending = true;
        input.value = '';
        input.disabled = true;

        addMessageBubble('user', message);
        addTypingIndicator();

        const wantsLinks = messageWantsLinks(message);

        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message, history: apiHistory(), language: currentLanguage }),
            });

            const data = await response.json();
            removeTypingIndicator();

            // A 200 covers both a real Groq answer AND the graceful
            // "AI unavailable, here's what I found" fallback (see
            // ChatbotService::fallbackResponse()) — both just render as
            // a normal assistant message. !response.ok here means an
            // actual HTTP-level failure (validation, rate limit, crash).
            if (!response.ok) {
                addMessageBubble('assistant', data.message || 'The assistant is temporarily unavailable. Please try again in a moment.');
            } else {
                const row = addMessageBubble('assistant', data.answer);

                if (wantsLinks) {
                    addResultLinks(row, data.sources);
                }

                pushTranscript({ role: 'user', content: message });
                pushTranscript({ role: 'assistant', content: data.answer, sources: data.sources, showLinks: wantsLinks });
            }
        } catch (error) {
            removeTypingIndicator();
            addMessageBubble('assistant', 'I could not reach the server. Please check your internet connection and try again.');
        } finally {
            input.disabled = false;
            input.focus();
            sending = false;
        }
    });
});
