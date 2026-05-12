# Guia d'Inicialització de XAMPP a Linux

Aquesta guia detalla els passos necessaris per iniciar, gestionar i configurar l'entorn XAMPP en un sistema Linux.

## 1. Iniciar XAMPP

Per iniciar tots els serveis (Apache, MySQL i ProFTPD), executa la següent comanda al terminal:

```bash
sudo /opt/lampp/lampp start
```

Si només vols iniciar serveis específics:

```bash
sudo /opt/lampp/lampp startapache
sudo /opt/lampp/lampp startmysql
```

## 2. Aturar XAMPP

Per aturar tots els serveis:

```bash
sudo /opt/lampp/lampp stop
```

## 3. Reiniciar XAMPP

Útil després de fer canvis en la configuració de PHP o Apache:

```bash
sudo /opt/lampp/lampp restart
```

## 4. Panell de Control Gràfic

XAMPP inclou una interfície gràfica per gestionar els serveis visualment:

```bash
cd /opt/lampp
sudo ./manager-linux-x64.run
```
*(Nota: El nom del fitxer pot variar segons la versió, e.g., `manager-linux.run`)*

## 5. Ubicacions Importants

- **Arrel del servidor web:** `/opt/lampp/htdocs/`
- **Fitxer de configuració de PHP (php.ini):** `/opt/lampp/etc/php.ini`
- **Fitxer de configuració d'Apache (httpd.conf):** `/opt/lampp/etc/httpd.conf`
- **Logs d'error de PHP:** `/opt/lampp/logs/php_error_log`
- **Logs d'error d'Apache:** `/opt/lampp/logs/error_log`

## 6. Gestió de la Base de Dades (CLI)

Per accedir al terminal de MySQL/MariaDB propi de XAMPP:

```bash
/opt/lampp/bin/mysql -u root
```

## 7. Solució de Problemes Comuns

### Error: Port 80 ocupat
Si Apache no inicia, pot ser que un altre servei estigui usant el port 80. Pots comprovar-ho amb:
```bash
sudo netstat -tulpn | grep :80
```

### Permisos de fitxers
Perquè WordPress pugui escriure a la carpeta `htdocs`, a vegades cal ajustar els permisos:
```bash
sudo chown -R daemon:daemon /opt/lampp/htdocs/wordpress
sudo chmod -R 755 /opt/lampp/htdocs/wordpress
```

---
*Document creat per Gemini CLI - Maig 2026*
