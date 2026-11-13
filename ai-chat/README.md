# AI Chat (React + Vite)

Aplikasi chat "study coach" yang ditampilkan oleh `chat.php` di dalam iframe.
AI-nya memakai [Puter.js](https://docs.puter.com) (`window.puter.ai.chat`), yang dimuat lewat `<script>` di `index.html`.

## File penting

| File            | Isi                                                        |
|-----------------|------------------------------------------------------------|
| `src/App.jsx`   | Seluruh logika & tampilan chat                             |
| `src/App.css`   | Tampilan chat                                              |
| `src/index.css` | Style dasar (+ Tailwind)                                   |
| `index.html`    | Halaman induk, memuat Puter.js                             |
| `dist/`         | Hasil build, yang dipakai oleh `chat.php`. Jangan diedit manual. |

## Perintah

```bash
npm install      # install dependency (cukup sekali)
npm run dev      # mode pengembangan
npm run build    # build ulang ke dist/ (wajib setelah mengubah kode)
npm run lint     # cek kesalahan penulisan kode
```

## Cara chat mengingat percakapan

Setiap kali user mengirim pesan, `App.jsx` mengirim **seluruh riwayat percakapan**
(maksimal 20 pesan terakhir) ke `puter.ai.chat()` dalam format:

```js
[
  { role: 'user', content: 'Quiz me on biology' },
  { role: 'assistant', content: 'Question 1: ...' },
  { role: 'user', content: 'The answer is B' },
]
```

Dengan begitu AI tahu konteks pesan sebelumnya, misalnya soal kuis yang baru saja dia berikan.
