# WP-AI-Guard: Seguretat Intel·ligent per a WordPress

WP-AI-Guard és un plugin de seguretat avançat que utilitza Intel·ligència Artificial per protegir el teu lloc de WordPress contra atacs comuns com SQL Injection i XSS.

## 🚀 Característiques Principals

- **Dual AI Engine:** Tria entre la potència de **Google Gemini Pro** o la privadesa de **Ollama** (IA Local).
- **Anàlisi en Temps Real:** Monitorització constant de peticions sospitoses.
- **Bloqueig Dinàmic:** Protecció automàtica basada en el "Threat Score" de la IP.
- **Whitelisting:** Protecció especial per a administradors.
- **Learning Mode:** Entrena el sistema sense afectar l'accessibilitat.

## 🛠️ Requisits

- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- XAMPP/LAMPP (per a entorn local).
- **Ollama** (opcional, per a la versió local gratuïta).

## 📦 Instal·lació i Ús

- **Configuració de l'Entorn:** Consulta la [Guia d'Inicialització de XAMPP a Linux](XAMPP_LINUX_GUIDE.md) per posar en marxa el servidor.
- **Detalls del Projecte:** Consulta el fitxer [INFORME_FINAL.md](INFORME_FINAL.md) per a una guia detallada pas a pas de la configuració del plugin.

## ⚙️ Configuració de l'IA

1. Ves al menú **WP-AI-Guard** a l'administració de WordPress.
2. Navega a la pestanya **Configuració**.
3. Selecciona el teu motor preferit:
   - **Gemini:** Introdueix la teva API Key de Google AI Studio.
   - **Ollama:** Assegura't que Ollama està corrent localment i especifica el model (ex: `llama3`).

---
*Projecte desenvolupat per a la millora de la seguretat en entorns WordPress.*
