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
  const [messages, setMessages] = useState([greeting]);
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
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [messages, isLoading]);

  const sendMessage = async (submittedMessage = inputValue) => {
    const message = submittedMessage.trim();
    if (!message || !aiReady || isLoading) {
      return;
    }

    const conversation = [...messages, { content: message, isUser: true }];
    setMessages(conversation);
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
      setMessages((current) => [...current, { content: reply, isUser: false }]);
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
    setMessages([greeting]);
    setInputValue('');
    setError('');
  };

  return (
    <main className="chat-app">
      <header className="chat-header">
        <div className="chat-avatar" aria-hidden="true">✧</div>
        <div className="chat-header-copy">
          <p className="eyebrow">YOUR STUDY COACH</p>
          <h1>What would you like to learn?</h1>
        </div>
        <button className="new-chat-button" type="button" onClick={startNewChat} disabled={isLoading}>
          New chat
        </button>
      </header>

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

      {messages.length === 1 && (
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
        Powered by Puter.js. Sign in when prompted. Messages are sent to Puter for AI processing and aren’t saved to your study account. Don’t share sensitive information.
      </p>
    </main>
  );
}

export default App;
