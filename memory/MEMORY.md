# Project Memory: WP-AI-Guard Development

## Current Status
The plugin is currently in a "Live Monitoring" phase with a dual AI engine (Gemini & Ollama). It captures SQLi, XSS, and Directory Traversal attempts.

## Implemented Features
- **Dual AI Engine:** Selector in settings for Google Gemini (API) vs Ollama (Local).
- **Asynchronous Analysis:** Uses WP-Cron (`wpguard_async_ai_analysis`) to prevent site slowdown.
- **Real-Time Dashboard:** AJAX-based polling every 5s in the admin panel with an interactive console.
- **Robust Parsing:** Regex-based JSON extraction from AI responses to handle conversational "noise".
- **Advanced Detection:** Decodes URLs and checks GET/POST values individually to avoid false positives from internal JSON strings.

## Known Issues & Fixes in Progress
- **Local IP Bias:** AI sometimes flags local IP (::1/127.0.0.1) requests as high risk. Fixed by updating prompt and adding pre-filtering logic.
- **Missed Attacks:** Some `curl` requests from the terminal are not being captured. Investigating if it's due to WordPress exit points or specific pattern mismatches.
- **Analysis Timeouts:** Local LLMs (Ollama) can be slow. Optimized by reducing batch size and increasing PHP limits.

## Technical Decisions
- **Non-blocking:** Priority #1 is user experience; AI analysis must never block page load.
- **Live UI:** The administrator's browser acts as the primary worker for manual analysis via AJAX.
- **Free/Premium Split:** Ollama is marketed as the "Free/Local" version, Gemini as "Premium/Cloud".

---
*Updated: May 11, 2026*
