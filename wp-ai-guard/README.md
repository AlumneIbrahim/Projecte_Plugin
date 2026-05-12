# WP-AI-Guard 🛡️

**WP-AI-Guard** és un connector de seguretat experimental per a WordPress que utilitza Intel·ligència Artificial (Google Gemini o Ollama local) per detectar i bloquejar trànsit maliciós en temps real.

## Característiques

-   **Monitorització en temps real**: Inspecciona totes les peticions entrants (GET/POST) a la recerca de patrons sospitosos com Injecció SQL i XSS.
-   **Anàlisi impulsat per IA**: S'integra amb l'API de Google Gemini o Ollama local per realitzar una anàlisi profunda dels logs sospitosos.
-   **Bloqueig Automàtic**: Bloqueja automàticament les adreces IP amb una puntuació de risc alta (> 7) determinada per la IA.
-   **Tauler de Seguretat**: Una pàgina d'administració dedicada per veure els logs, els nivells d'amenaça i les explicacions de la IA amb recomanacions d'accions.
-   **Modo Aprenentatge**: Opció per registrar activitat sense bloquejar, ideal per a configuracions inicials.
-   **Multilingüe**: La IA respon en l'idioma configurat al teu WordPress.

## Instal·lació

1.  **Baixar/Clonar**: Puja la carpeta `wp-ai-guard` al teu directori `/wp-content/plugins/`.
2.  **Activar**: Ves al menú de 'Connectors' al teu tauler d'administració de WordPress i fes clic a 'Activar' per a WP-AI-Guard.
3.  **Configurar el motor d'IA**:
    Pots triar entre Google Gemini (Online) o Ollama (Local).
    -   **Google Gemini**: Necessitaràs una clau d'API de [Google AI Studio](https://aistudio.google.com/). Pots afegir-la a `wp-config.php` o al tauler de configuració:
        ```php
        define( 'WP_AI_GUARD_API_KEY', 'la_teva_clau_aqui' );
        ```
    -   **Ollama**: Instal·la Ollama al teu servidor i configura el model (ex: llama3) al tauler de configuració del connector.

## Com funciona

1.  **Detecció de Patrons**: El connector monitoritza cada petició. Si detecta caràcters com `<`, `>`, `'` o paraules clau SQL (`SELECT`, `UNION`, etc.), registra la petició com a "sospitosa".
2.  **Verificació per IA**: El connector envia el log sospitós a la IA amb un prompt especialitzat.
3.  **Puntuació de Risc**: La IA retorna una resposta en format JSON amb un `threat_level` (0-10), el tipus d'atac i una explicació tècnica amb recomanacions.
4.  **Execució**: En cada càrrega de pàgina, el connector comprova la base de dades i la memòria cau (transients). Si una IP té un historial amb una puntuació superior a 8, l'usuari és bloquejat immediatament amb un missatge de 403 Forbidden.

## Esquema de la Base de Dades

El connector crea una taula personalitzada `{prefix}wpguard_logs` amb els següents camps:
- `id`: Clau primària.
- `ip`: Adreça IP del visitant.
- `request_data`: Dades de la URL i POST codificades en JSON.
- `threat_score`: Puntuació numèrica (0-10).
- `ai_analysis`: Tipus d'atac, explicació i recomanació de la IA.
- `status`: Estat de l'anàlisi (pending, processing, completed).
- `created_at`: Marca de temps.

## Avís de Seguretat

*Aquest connector té finalitats educatives i experimentals. Utilitzeu sempre solucions de seguretat consolidades com Wordfence o Cloudflare per a entorns de producció.*

## Llicència

GPLv2 o posterior.
