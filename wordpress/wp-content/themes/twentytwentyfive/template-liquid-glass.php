<?php
/*
Template Name: Liquid Glass Landing
*/
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body class="home-page-custom">
	<div class="hero-liquid-bg"></div>

	<nav class="glass-navbar">
		<a href="#"><?php _e( 'Inici', 'wp-ai-guard' ); ?></a>
		<a href="#features"><?php _e( 'Característiques', 'wp-ai-guard' ); ?></a>
		<a href="#monitor"><?php _e( 'Monitor', 'wp-ai-guard' ); ?></a>
		<a href="/wordpress/wp-admin/admin.php?page=wp-ai-guard-status"><?php _e( 'Panell d\'Administració', 'wp-ai-guard' ); ?></a>
	</nav>

	<header class="lg-section">
		<h1 class="lg-hero-title">WP-AI-Guard</h1>
		<h2 class="lg-hero-subtitle"><?php _e( 'La seguretat de WordPress, reimaginada amb Intel·ligència Artificial.', 'wp-ai-guard' ); ?></h2>
		<p class="lg-hero-desc"><?php _e( 'Protegeix el teu lloc web en temps real contra injeccions SQL, XSS i atacs de força bruta utilitzant el poder de Google Gemini i Ollama.', 'wp-ai-guard' ); ?></p>
		<a href="#features" class="lg-btn"><?php _e( 'Descobreix més', 'wp-ai-guard' ); ?></a>
	</header>

	<section id="features" class="lg-section">
		<div class="lg-grid">
			<div class="lg-glass-card">
				<span class="lg-card-icon dashicons dashicons-shield"></span>
				<h3 class="lg-card-title"><?php _e( 'Monitoratge en Temps Real', 'wp-ai-guard' ); ?></h3>
				<p class="lg-card-text"><?php _e( 'Inspecció instantània de cada petició HTTP per detectar patrons maliciosos abans que arribin a la teva base de dades.', 'wp-ai-guard' ); ?></p>
			</div>
			<div class="lg-glass-card">
				<span class="lg-card-icon dashicons dashicons-brainstorming"></span>
				<h3 class="lg-card-title"><?php _e( 'Anàlisi Neuronal', 'wp-ai-guard' ); ?></h3>
				<p class="lg-card-text"><?php _e( 'Utilitza models de llenguatge avançats per analitzar el context dels atacs i rebre recomanacions de seguretat precises.', 'wp-ai-guard' ); ?></p>
			</div>
			<div class="lg-glass-card">
				<span class="lg-card-icon dashicons dashicons-lock"></span>
				<h3 class="lg-card-title"><?php _e( 'Bloqueig Intel·ligent', 'wp-ai-guard' ); ?></h3>
				<p class="lg-card-text"><?php _e( 'Bloqueig automàtic d\'IPs basat en el nivell de risc detectat, amb sistema de memòria cau per a un rendiment òptim.', 'wp-ai-guard' ); ?></p>
			</div>
		</div>
	</section>

	<section id="monitor" class="lg-section">
		<div class="lg-glass-card" style="text-align: center;">
			<h2 class="lg-card-title" style="font-size: 32px;"><?php _e( 'Tauler de Control Modern', 'wp-ai-guard' ); ?></h2>
			<p class="lg-card-text" style="margin-bottom: 40px;"><?php _e( 'Una interfície neta i intuïtiva per gestionar la seguretat del teu lloc des de qualsevol dispositiu.', 'wp-ai-guard' ); ?></p>
			<div style="background: rgba(0,0,0,0.5); border-radius: 16px; padding: 10px; border: 1px solid rgba(255,255,255,0.1);">
				<img src="/wordpress/wp-content/plugins/wp-ai-guard/admin/screenshot.png" alt="WP-AI-Guard Dashboard" style="width: 100%; border-radius: 8px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
			</div>
		</div>
	</section>

	<footer class="lg-section" style="padding-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); opacity: 0.6;">
		<p>&copy; <?php echo date('Y'); ?> WP-AI-Guard. <?php _e( 'Seguretat impulsada per IA.', 'wp-ai-guard' ); ?></p>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>
