# Informe Final i Guia d'Instal·lació: WP-AI-Guard

Aquest document conté el resum del projecte i el tutorial pas a pas per posar en marxa l'entorn i el plugin de seguretat.

---

## 1. Guia d'Instal·lació Pas a Pas

Segueix aquests passos per configurar l'entorn des de zero:

### Pas 1: Instal·lació de XAMPP (Servidor Local)
1. **Descarrega XAMPP:** Ves a la pàgina oficial d'Apache Friends i descarrega la versió per al teu sistema operatiu (Windows, Linux o macOS).
2. **Instal·lació:** Executa l'instal·lador i assegura't que els mòduls **Apache** i **MySQL** estiguin seleccionats.
3. **Arrencada:** Obre el tauler de control de XAMPP i clica a "Start" per a Apache i MySQL.
   - *Nota a Linux:* S'executa amb `sudo /opt/lampp/lampp start`.

### Pas 2: Configuració de la Base de Dades
1. Obre el teu navegador i ves a `http://localhost/phpmyadmin`.
2. Clica a la pestanya **"Bases de dades"**.
3. Crea una nova base de dades anomenada **`wordpress_db`**.
4. No cal configurar contrasenya per defecte (l'usuari serà `root` i la contrasenya buida).

### Pas 3: Instal·lació de WordPress
1. **Descarrega WordPress:** Baixa l'última versió de `wordpress.org`.
2. **Ubicació:** Copia la carpeta `wordpress` dins del directori `htdocs` de XAMPP:
   - Windows: `C:\xampp\htdocs\wordpress`
   - Linux: `/opt/lampp/htdocs/wordpress`
3. **Configuració inicial:**
   - Renomena el fitxer `wp-config-sample.php` a `wp-config.php`.
   - Edita el fitxer i posa les dades de la base de dades:
     ```php
     define( 'DB_NAME', 'wordpress_db' );
     define( 'DB_USER', 'root' );
     define( 'DB_PASSWORD', '' );
     define( 'DB_HOST', 'localhost' );
     ```
4. **Finalització:** Ves a `http://localhost/wordpress` al navegador i segueix els passos de l'assistent d'instal·lació.

### Pas 4: Instal·lació del Plugin WP-AI-Guard
1. **Còpia del plugin:** Copia la carpeta `wp-ai-guard` (inclosa en aquest .zip) dins de:
   - `wordpress/wp-content/plugins/`
2. **Activació:**
   - Entra al panell d'administració de WordPress (`http://localhost/wordpress/wp-admin`).
   - Ves a la secció **Plugins**.
   - Busca **WP-AI-Guard** i clica a **Activar**.
3. **Verificació:** Apareixerà un nou menú anomenat "AI Guard" a la barra lateral on podràs veure els logs i la configuració.

---

## 2. Resum del Projecte WP-AI-Guard

El plugin **WP-AI-Guard** és una solució de seguretat intel·ligent per a WordPress que:
- **Analitza el trànsit:** Detecta patrons d'atac SQLi i XSS en temps real.
- **Puntuació de Risc:** Assigna un "Threat Score" a cada IP basat en el seu comportament.
- **Bloqueig Automàtic:** Si una IP acumula més de 3 incidències amb un risc alt en una hora, és bloquejada automàticament.
- **Mode Aprenentatge:** Permet provar el plugin sense bloquejar ningú, només registrant els atacs.
- **Seguretat per a l'Admin:** Inclou una whitelist automàtica per evitar que l'administrador es bloquegi per error.

---
*Creat per l'assistent Gemini CLI - Maig 2026*
