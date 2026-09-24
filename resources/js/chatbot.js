// "Sage" floating chat widget — v2 (slide-in panel).
// Talks to POST /api/chat (App\Http\Controllers\ChatbotController).
// Conversation persisted to sessionStorage per user (see STORAGE_KEY).
document.addEventListener('DOMContentLoaded', function () {
    // New v2 element IDs
    const fab = document.getElementById('sh-sage-fab');
    const panel = document.getElementById('sh-sage-panel');
    const closeBtn = document.getElementById('sh-sage-close');
    const backdrop = document.getElementById('sh-panel-backdrop');
    const newChatBtn = document.getElementById('chatbot-new-chat');
    const languageSelect = document.getElementById('chatbot-language');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');

    if (!fab || !panel || !messages) return;

    const greetingHtml = messages.innerHTML;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const MAX_HISTORY = 20;
    const MAX_HISTORY_CHARS = 2000;

    const userId = document.querySelector('meta[name="auth-user-id"]')?.content || 'guest';
    const STORAGE_KEY = `sage-chat-transcript-${userId}`;
    const LANGUAGE_STORAGE_KEY = `sage-chat-language-${userId}`;
    const VALID_LANGUAGES = ['english', 'tagalog', 'taglish'];

    let transcript = loadTranscript();
    let sending = false;
    let currentLanguage = loadLanguage();
    languageSelect.value = currentLanguage;

    const sendBtn = form.querySelector('.sh-sage-send-btn');
    if (sendBtn) {
        sendBtn.disabled = true;
        input.addEventListener('input', function () {
            sendBtn.disabled = !input.value.trim();
        });
    }

    languageSelect.addEventListener('change', function () {
        currentLanguage = languageSelect.value;
        saveLanguage(currentLanguage);
    });

    function loadTranscript() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (error) {
            return [];
        }
    }

    function saveTranscript() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(transcript));
        } catch (error) {
            // Storage full/blocked
        }
    }

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
            // Storage full/blocked
        }
    }

    // --- Panel open/close ---
    function openPanel() {
        panel.classList.add('is-open');
        backdrop.classList.add('is-visible');
        backdrop.style.display = 'block';
        fab.querySelector('i').className = 'bi bi-x-lg';
        fab.setAttribute('aria-label', 'Close Sage');
        document.body.style.overflow = 'hidden';
        setTimeout(() => input.focus(), 250);
    }

    function closePanel() {
        panel.classList.remove('is-open');
        backdrop.classList.remove('is-visible');
        fab.querySelector('i').className = 'bi bi-chat-dots-fill';
        fab.setAttribute('aria-label', 'Open Sage');
        document.body.style.overflow = '';
        setTimeout(function () {
            backdrop.style.display = '';
        }, 250);
    }

    function togglePanel() {
        if (panel.classList.contains('is-open')) {
            closePanel();
        } else {
            openPanel();
        }
    }

    fab.addEventListener('click', togglePanel);
    closeBtn.addEventListener('click', closePanel);
    backdrop.addEventListener('click', closePanel);

    // Mobile bottom tab bar Sage button
    const sageMobileBtn = document.querySelector('.sh-sage-mobile-btn');
    if (sageMobileBtn) {
        sageMobileBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openPanel();
        });
    }

    // Escape key closes panel
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) {
            closePanel();
        }
    });

    function resetConversation() {
        transcript = [];
        saveTranscript();
        messages.innerHTML = greetingHtml;
    }

    newChatBtn.addEventListener('click', function () {
        if (confirm('Start a new chat? This will clear your current conversation with Sage.')) {
            resetConversation();
        }
    });

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function addMessageBubble(role, text) {
        const row = document.createElement('div');
        row.className = `sh-sage-msg sh-sage-msg-${role} sh-sage-msg-in`;

        const contentRow = document.createElement('div');
        contentRow.className = 'sh-sage-msg-row';

        if (role === 'assistant') {
            const avatar = document.createElement('div');
            avatar.className = 'sh-sage-msg-avatar';
            avatar.innerHTML = '<i class="bi bi-mortarboard-fill"></i>';
            contentRow.appendChild(avatar);
        }

        const bubble = document.createElement('div');
        bubble.className = 'sh-sage-bubble';

        if (role === 'assistant') {
            bubble.innerHTML = formatAssistantHtml(text);
        } else {
            bubble.textContent = text;
        }

        contentRow.appendChild(bubble);
        row.appendChild(contentRow);

        const time = document.createElement('div');
        time.className = 'sh-sage-msg-time';
        time.textContent = formatTime();

        messages.appendChild(row);
        messages.appendChild(time);
        scrollToBottom();

        return row;
    }

    function formatTime() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${hours}:${minutes} ${ampm}`;
    }

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
                    html.push('<ul class="sh-sage-bubble-list">');
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
        if (!results || results.length === 0) return;

        const list = document.createElement('div');
        list.className = 'sh-sage-results';

        results.forEach(function (result) {
            const files = result.syllabus_files || [];

            if (files.length === 0) {
                list.appendChild(buildResultLink(
                    `/courses/${result.course_id}`,
                    `${result.course_code} — ${result.title}`,
                    'bi bi-file-earmark-x'
                ));
                return;
            }

            files.forEach(function (file) {
                const ext = file.file_type.toUpperCase();
                const fileIcon = ext === 'PDF' ? 'bi bi-file-earmark-pdf' : 'bi bi-file-earmark-word';
                list.appendChild(buildResultLink(
                    `/syllabi/${file.id}/download`,
                    `${result.course_code} — ${result.title} (${ext})`,
                    fileIcon
                ));
            });
        });

        row.appendChild(list);
        scrollToBottom();
    }

    function buildResultLink(href, label, iconClass) {
        const link = document.createElement('a');
        link.className = 'sh-sage-result-link';
        link.href = href;
        link.target = '_blank';
        link.rel = 'noopener';

        const iconWrap = document.createElement('div');
        iconWrap.className = 'sh-sage-result-icon';
        const icon = document.createElement('i');
        icon.className = iconClass;
        iconWrap.appendChild(icon);

        const info = document.createElement('div');
        info.className = 'sh-sage-result-info';

        const name = document.createElement('div');
        name.className = 'sh-sage-result-name';
        name.textContent = label;

        const parts = label.split(' — ');
        if (parts.length > 1) {
            name.textContent = parts[0];
            const meta = document.createElement('div');
            meta.className = 'sh-sage-result-meta';
            meta.textContent = parts[1];
            info.appendChild(name);
            info.appendChild(meta);
        } else {
            info.appendChild(name);
        }

        const dlBtn = document.createElement('div');
        dlBtn.className = 'sh-sage-result-download';
        const dlIcon = document.createElement('i');
        dlIcon.className = 'bi bi-download';
        dlBtn.appendChild(dlIcon);

        link.appendChild(iconWrap);
        link.appendChild(info);
        link.appendChild(dlBtn);

        return link;
    }

    function addTypingIndicator() {
        const row = document.createElement('div');
        row.className = 'sh-sage-msg sh-sage-msg-assistant sh-sage-msg-in';
        row.id = 'chatbot-typing';

        const contentRow = document.createElement('div');
        contentRow.className = 'sh-sage-msg-row';

        const avatar = document.createElement('div');
        avatar.className = 'sh-sage-msg-avatar';
        avatar.innerHTML = '<i class="bi bi-mortarboard-fill"></i>';
        contentRow.appendChild(avatar);

        const bubble = document.createElement('div');
        bubble.className = 'sh-sage-bubble sh-sage-spinner-bubble';
        bubble.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-label="Sage is typing…"></span>';
        contentRow.appendChild(bubble);

        row.appendChild(contentRow);
        messages.appendChild(row);
        scrollToBottom();
    }

    function removeTypingIndicator() {
        document.getElementById('chatbot-typing')?.remove();
    }

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

    function apiHistory() {
        return transcript.map(({ role, content }) => ({ role, content }));
    }

    function messageWantsLinks(message) {
        return /\b(link|links|download|pdf|docx|file|files|send|ipadala|padala|ibigay|bigay|kunin|buksan|open|share|attach)\b/i.test(message);
    }

    function restoreTranscript() {
        if (transcript.length === 0) return;

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
        if (!message || sending) return;

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
            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
            }
            input.disabled = false;
            input.focus();
            sending = false;
        }
    });
});
