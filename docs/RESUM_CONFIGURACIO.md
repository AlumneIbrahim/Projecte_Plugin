# Resum de Configuració del Projecte WordPress

Aquest document detalla les accions realitzades per a la posada en marxa de l'entorn de desenvolupament i la configuració del plugin WP-AI-Guard.

## 1. Instal·lació de l'Entorn (XAMPP/LAMPP)
- **Serveis:** S'han activat els serveis d'Apache i MySQL mitjançant XAMPP situat a `/opt/lampp`.
- **Accés Web:** El servidor està configurat per respondre a `http://localhost`.
- **phpMyAdmin:** Disponible a `http://localhost/phpmyadmin` per a la gestió visual de les bases de dades.

## 2. Base de Dades
- **Nom:** `wordpress_db`
- **Usuari:** `root`
- **Contrasenya:** (buida)
- **Prefix de taules:** `wp_`
- La base de dades s'ha verificat i està llista per a l'ús de WordPress.

## 3. Instal·lació de WordPress
- **Ubicació:** El codi de WordPress s'ha mogut a `/opt/lampp/htdocs/wordpress` per garantir la compatibilitat de permisos i l'accés des de `http://localhost/wordpress`.
- **Configuració:** S'ha editat el fitxer `wp-config.php` amb les credencials de la base de dades esmentades anteriorment.

## 4. Desenvolupament del Plugin: WP-AI-Guard
S'han implementat millores crítiques de seguretat al plugin:
- **Whitelist d'Administradors:** Evita el bloqueig accidental d'usuaris amb permisos de gestió.
- **Mode Aprenentatge (`WP_AI_LEARNING_MODE`):** Permet registrar atacs sense bloquejar la IP per a la calibració de la IA.
- **Llindar Dinàmic de Bloqueig:** Les IPs només es bloquegen si tenen més de 3 incidències en una hora amb un "threat_score" mitjà superior a 8.
- **Sincronització Dual:** Flux de treball configurat per mantenir actualitzats tant el repositori Git com el servidor en viu.

---
*Generat per Gemini CLI el 10 de maig de 2026.*
