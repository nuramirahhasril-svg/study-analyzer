import { useEffect, useRef, useState } from 'react';
import './App.css';

const greeting = {
  content: 'Hi! I can explain tricky topics, quiz you, or help you plan a study session. What are you working on?',
  isUser: false,
};

const suggestions = [
  'Explain a difficult topic step by step.',
  'Quiz me on a subject I am studying.',
  'Help me make a focused 25-minute study plan.',
];

// Berapa banyak pesan terakhir yang ikut dikirim ke AI (supaya tidak terlalu panjang).
const MAX_HISTORY_MESSAGES = 20;

// Riwayat chat disimpan di perangkat (localStorage), maksimal sekian percakapan.
const STORAGE_PREFIX = 'studycoach.chats.v1';
const MAX_SAVED_CHATS = 50;

/** Website mengirim ?u=<id user> supaya riwayat tiap akun terpisah di browser yang sama. */
function getStorageKey() {
  const user = new URLSearchParams(window.location.search).get('u');
  return user ? `${STORAGE_PREFIX}.${user}` : STORAGE_PREFIX;
}

function loadChats() {
  try {
    const parsed = JSON.parse(window.localStorage.getItem(getStorageKey()) ?? '[]');
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed
      .filter((chat) => chat && typeof chat.id === 'string' && Array.isArray(chat.messages))
      .sort((a, b) => b.updatedAt - a.updatedAt);
  } catch {
    return [];
  }
}

function saveChats(chats) {
  try {
    window.localStorage.setItem(getStorageKey(), JSON.stringify(chats.slice(0, MAX_SAVED_CHATS)));
  } catch {
    // Penyimpanan penuh / diblokir: chat tetap jalan, hanya tidak tersimpan.
  }
}

function makeChatId() {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

function makeTitle(messages) {
  const first = messages.find((message) => message.isUser)?.content.trim() ?? 'New chat';
  return first.length > 48 ? `${first.slice(0, 48).trimEnd()}…` : first;
}

function formatChatDate(timestamp) {
  const date = new Date(timestamp);
  const now = new Date();
  if (date.toDateString() === now.toDateString()) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return date.toLocaleDateString([], { day: 'numeric', month: 'short', year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
}

/**
 * Ubah daftar pesan di layar menjadi format percakapan untuk Puter:
 * [{ role: 'user', content: '...' }, { role: 'assistant', content: '...' }, ...]
 * Dengan mengirim riwayat ini, AI "ingat" pertanyaan & jawaban sebelumnya
 * (misalnya soal kuis yang baru saja dia berikan).
 */
function toPuterMessages(chatMessages) {
  const history = chatMessages
    .filter((message) => message !== greeting) // sapaan awal hanya tampilan, tidak dikirim
    .slice(-MAX_HISTORY_MESSAGES)
    .map((message) => ({
      role: message.isUser ? 'user' : 'assistant',
      content: message.content,
    }));

  // Percakapan harus diawali pesan dari user.
  const firstUserIndex = history.findIndex((message) => message.role === 'user');
  return history.slice(firstUserIndex);
}

function getErrorMessage(error) {
  if (error?.error?.code === 'auth_canceled') {
    return 'Puter sign-in was canceled. Try again and complete the sign-in prompt to use the AI chat.';
  }
  if (typeof error?.error?.message === 'string') {
    return error.error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  if (typeof error?.message === 'string') {
    return error.message;
  }
  return 'Unable to get a response from the AI study coach. Please try again.';
}

function getResponseText(response) {
  const content = typeof response === 'string'
    ? response
    : response?.message?.content ?? response?.text ?? '';

  if (Array.isArray(content)) {
    return content.map((part) => (
      typeof part === 'string' ? part : typeof part?.text === 'string' ? part.text : ''
    )).join('').trim();
  }

  return typeof content === 'string' ? content.trim() : '';
}

function App() {
  // Semua percakapan tersimpan, terbaru di atas.
  const [chats, setChats] = useState(loadChats);
  // Chat yang sedang dibuka (null = chat baru yang belum ada pesannya).
  // Saat halaman dibuka, chat terakhir langsung ditampilkan lagi.
  const [activeChatId, setActiveChatId] = useState(() => chats[0]?.id ?? null);
  const [showHistory, setShowHistory] = useState(false);
  const activeChat = chats.find((chat) => chat.id === activeChatId);
  const messages = [greeting, ...(activeChat?.messages ?? [])];
  const [inputValue, setInputValue] = useState('');
  const [aiReady, setAiReady] = useState(
    () => typeof window.puter?.ai?.chat === 'function',
  );
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const messagesEndRef = useRef(null);

  useEffect(() => {
    if (aiReady) {
      return undefined;
    }

    const checkReady = window.setInterval(() => {
      if (typeof window.puter?.ai?.chat === 'function') {
        setAiReady(true);
        setError('');
        window.clearInterval(checkReady);
      }
    }, 300);
    const timeout = window.setTimeout(() => {
      window.clearInterval(checkReady);
      setError('Could not connect to Puter.js. Check your internet connection and refresh this page.');
    }, 20000);

    return () => {
      window.clearInterval(checkReady);
      window.clearTimeout(timeout);
    };
  }, [aiReady]);

  useEffect(() => {
    saveChats(chats);
  }, [chats]);

  const messageCount = messages.length;
  useEffect(() => {
    if (!showHistory) {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
  }, [activeChatId, messageCount, isLoading, showHistory]);

  /** Tambahkan pesan ke chat tertentu dan pindahkan chat itu ke paling atas. */
  const appendToChat = (chatId, newMessage) => {
    setChats((current) => {
      const existing = current.find((chat) => chat.id === chatId);
      const chatMessages = [...(existing?.messages ?? []), newMessage];
      const updated = {
        id: chatId,
        title: existing?.title ?? makeTitle(chatMessages),
        createdAt: existing?.createdAt ?? Date.now(),
        updatedAt: Date.now(),
        messages: chatMessages,
      };
      return [updated, ...current.filter((chat) => chat.id !== chatId)];
    });
  };

  const sendMessage = async (submittedMessage = inputValue) => {
    const message = submittedMessage.trim();
    if (!message || !aiReady || isLoading) {
      return;
    }

    const chatId = activeChatId ?? makeChatId();
    const userMessage = { content: message, isUser: true };
    const conversation = [...messages, userMessage];
    setActiveChatId(chatId);
    appendToChat(chatId, userMessage);
    setInputValue('');
    setError('');
    setIsLoading(true);

    try {
      // Kirim seluruh percakapan, bukan hanya pesan terakhir.
      const response = await window.puter.ai.chat(toPuterMessages(conversation));
      const reply = getResponseText(response);
      if (!reply) {
        throw new Error('The AI study coach returned an empty response. Please try again.');
      }
      appendToChat(chatId, { content: reply, isUser: false });
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsLoading(false);
    }
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    sendMessage();
  };

  const handleKeyDown = (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      event.currentTarget.form.requestSubmit();
    }
  };

  const startNewChat = () => {
    if (isLoading) {
      return;
    }
    setActiveChatId(null);
    setShowHistory(false);
    setInputValue('');
    setError('');
  };

  const openChat = (chatId) => {
    if (isLoading) {
      return;
    }
    setActiveChatId(chatId);
    setShowHistory(false);
    setError('');
  };

  const deleteChat = (chat) => {
    if (isLoading || !window.confirm(`Delete "${chat.title}"? This can't be undone.`)) {
      return;
    }
    setChats((current) => current.filter((item) => item.id !== chat.id));
    if (chat.id === activeChatId) {
      setActiveChatId(null);
    }
  };

  return (
    <main className="chat-app">
      <header className="chat-header">
        <div className="chat-avatar" aria-hidden="true">✧</div>
        <div className="chat-header-copy">
          <p className="eyebrow">YOUR STUDY COACH</p>
          <h1>{showHistory ? 'Chat history' : activeChat?.title ?? 'What would you like to learn?'}</h1>
        </div>
        <div className="chat-header-actions">
          <button
            className="history-button"
            type="button"
            onClick={() => setShowHistory((open) => !open)}
            disabled={isLoading}
            aria-pressed={showHistory}
          >
            {showHistory ? 'Back to chat' : `History${chats.length ? ` (${chats.length})` : ''}`}
          </button>
          <button className="new-chat-button" type="button" onClick={startNewChat} disabled={isLoading}>
            New chat
          </button>
        </div>
      </header>

      {showHistory ? (
        <section className="chat-history" aria-label="Chat history">
          {chats.length === 0 ? (
            <p className="history-empty">No saved chats yet. Your conversations will show up here.</p>
          ) : (
            <ul>
              {chats.map((chat) => (
                <li key={chat.id} className={chat.id === activeChatId ? 'active' : undefined}>
                  <button className="history-open" type="button" onClick={() => openChat(chat.id)}>
                    <span className="history-title">{chat.title}</span>
                    <span className="history-meta">
                      {formatChatDate(chat.updatedAt)} · {chat.messages.length} message{chat.messages.length === 1 ? '' : 's'}
                    </span>
                  </button>
                  <button
                    className="history-delete"
                    type="button"
                    onClick={() => deleteChat(chat)}
                    aria-label={`Delete chat: ${chat.title}`}
                    title="Delete chat"
                  >
                    ×
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      ) : (
      <section className="chat-messages" aria-label="Chat messages" aria-live="polite">
        {messages.map((message, index) => (
          <article
            className={`chat-message ${message.isUser ? 'user-message' : 'assistant-message'}`}
            key={`${index}-${message.isUser ? 'user' : 'assistant'}`}
          >
            <span className="message-label">{message.isUser ? 'You' : 'Study coach'}</span>
            <p>{message.content}</p>
          </article>
        ))}
        {isLoading && (
          <article className="chat-message assistant-message typing-message">
            <span className="message-label">Study coach</span>
            <p>Thinking…</p>
          </article>
        )}
        <div ref={messagesEndRef} />
      </section>
      )}

      {!showHistory && messages.length === 1 && (
        <div className="chat-suggestions" aria-label="Suggested questions">
          {suggestions.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              onClick={() => sendMessage(suggestion)}
              disabled={!aiReady || isLoading}
            >
              {suggestion}
            </button>
          ))}
        </div>
      )}

      <form className="chat-form" onSubmit={handleSubmit}>
        <label className="visually-hidden" htmlFor="chat-input">Message your study coach</label>
        <textarea
          id="chat-input"
          value={inputValue}
          onChange={(event) => setInputValue(event.target.value)}
          onKeyDown={handleKeyDown}
          maxLength={4000}
          rows={2}
          placeholder="Ask a question about what you're studying…"
          disabled={!aiReady || isLoading}
          required
        />
        <button
          className="send-button"
          type="submit"
          disabled={!aiReady || isLoading || !inputValue.trim()}
        >
          Send <span aria-hidden="true">↑</span>
        </button>
      </form>

      <p className={`chat-status${error ? ' error' : ''}`} role="status">
        {error || (!aiReady ? 'Connecting to the AI study coach…' : '')}
      </p>
      <p className="privacy-note">
        Powered by Puter.js. Sign in when prompted. Messages are sent to Puter for AI processing. Chat history is saved only on this device, not in your study account. Don’t share sensitive information.
      </p>
    </main>
  );
}

export default App;
