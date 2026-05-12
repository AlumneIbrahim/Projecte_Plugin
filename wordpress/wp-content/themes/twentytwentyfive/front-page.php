<?php
/**
 * Front page template for the Liquid Glass aesthetic.
 * Forces the high-end Apple-inspired design.
 */

// If we are not on the front page, fall back to the default behavior (though this file shouldn't be loaded then)
if ( ! is_front_page() ) {
	include get_query_template( 'index' );
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		/* FORCED LIQUID GLASS STYLES */
		:root {
			--lg-primary: #0071e3;
			--lg-bg: #000000;
			--lg-glass-white: rgba(255, 255, 255, 0.08);
			--lg-glass-border: rgba(255, 255, 255, 0.15);
			--lg-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);
		}

		body {
			background: #000 !important;
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
			-webkit-font-smoothing: antialiased;
			color: #fff !important;
			overflow-x: hidden;
		}

		.hero-liquid-bg {
			position: fixed;
			top: 0;
			left: 0;
			width: 100vw;
			height: 100vh;
			background: linear-gradient(-45deg, #000428, #002e5c, #000000, #0a192f);
			background-size: 400% 400%;
			animation: liquid-gradient 12s ease infinite;
			z-index: -1;
		}

		@keyframes liquid-gradient {
			0% { background-position: 0% 50%; }
			50% { background-position: 100% 50%; }
			100% { background-position: 0% 50%; }
		}

		.glass-navbar {
			position: fixed;
			top: 0;
			width: 100%;
			height: 52px;
			background: rgba(0, 0, 0, 0.7);
			backdrop-filter: saturate(180%) blur(25px);
			-webkit-backdrop-filter: saturate(180%) blur(25px);
			z-index: 1000;
			display: flex;
			align-items: center;
			justify-content: center;
			border-bottom: 0.5px solid rgba(255,255,255,0.15);
		}

		.glass-navbar a {
			color: #f5f5f7;
			text-decoration: none;
			font-size: 13px;
			font-weight: 400;
			opacity: 0.8;
			transition: all 0.3s ease;
			margin: 0 25px;
			letter-spacing: -0.01em;
		}

		.glass-navbar a:hover { opacity: 1; color: var(--lg-primary); }

		.lg-section {
			max-width: 1100px;
			margin: 0 auto;
			padding: 140px 24px;
			text-align: center;
			position: relative;
			z-index: 1;
		}

		.lg-hero-title {
			font-size: clamp(48px, 10vw, 86px);
			font-weight: 700;
			letter-spacing: -0.03em;
			line-height: 1.02;
			margin-bottom: 24px;
			background: linear-gradient(180deg, #ffffff 0%, #8e8e93 100%);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			animation: fadeInUp 1s ease-out;
		}

		.lg-hero-subtitle {
			font-size: clamp(20px, 4vw, 32px);
			font-weight: 600;
			color: #fff;
			max-width: 850px;
			margin: 0 auto 32px auto;
			line-height: 1.1;
			letter-spacing: -0.01em;
			animation: fadeInUp 1.2s ease-out;
		}

		.lg-hero-desc {
			font-size: clamp(17px, 2vw, 21px);
			color: #a1a1a6;
			max-width: 750px;
			margin: 0 auto 48px auto;
			line-height: 1.45;
			animation: fadeInUp 1.4s ease-out;
		}

		.lg-glass-card {
			background: rgba(255, 255, 255, 0.04);
			backdrop-filter: blur(30px) saturate(180%);
			-webkit-backdrop-filter: blur(30px) saturate(180%);
			border-radius: 30px;
			border: 1px solid rgba(255, 255, 255, 0.12);
			padding: 48px;
			box-shadow: var(--lg-shadow);
			transition: all 0.5s cubic-bezier(0.2, 1, 0.3, 1);
			text-align: left;
		}

		.lg-glass-card:hover {
			transform: translateY(-10px);
			background: rgba(255, 255, 255, 0.08);
			border-color: rgba(255, 255, 255, 0.25);
			box-shadow: 0 15px 45px rgba(0, 0, 0, 0.5);
		}

		.lg-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
			gap: 32px;
			margin-top: 100px;
		}

		.lg-card-icon {
			font-size: 36px;
			margin-bottom: 28px;
			display: block;
			color: var(--lg-primary);
		}

		.lg-card-title {
			font-size: 26px;
			font-weight: 600;
			margin-bottom: 18px;
			color: #fff;
			letter-spacing: -0.02em;
		}

		.lg-card-text {
			font-size: 17px;
			color: #a1a1a6;
			line-height: 1.55;
		}

		.lg-btn {
			display: inline-block;
			background: var(--lg-primary);
			color: #fff;
			padding: 16px 32px;
			border-radius: 980px;
			font-size: 17px;
			font-weight: 500;
			text-decoration: none;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		}

		.lg-btn:hover {
			background: #0077ed;
			transform: scale(1.05);
			box-shadow: 0 0 20px rgba(0, 113, 227, 0.4);
		}

		@keyframes fadeInUp {
			from { opacity: 0; transform: translateY(30px); }
			to { opacity: 1; transform: translateY(0); }
		}

		#monitor-preview img {
			width: 100%;
			border-radius: 20px;
			box-shadow: 0 30px 60px rgba(0,0,0,0.6);
			border: 1px solid rgba(255,255,255,0.1);
			transition: transform 0.6s ease;
		}

		#monitor-preview:hover img {
			transform: scale(1.01) translateY(-5px);
		}
		
		/* Remove WP admin bar space if present */
		html { margin-top: 0 !important; }
		* { box-sizing: border-box; }
	</style>
</head>
<body>
	<div class="hero-liquid-bg"></div>

	<nav class="glass-navbar">
		<a href="#"><?php _e( 'Inici', 'wp-ai-guard' ); ?></a>
		<a href="#features"><?php _e( 'Funcions', 'wp-ai-guard' ); ?></a>
		<a href="#monitor"><?php _e( 'Monitor', 'wp-ai-guard' ); ?></a>
		<a href="/wordpress/wp-admin/admin.php?page=wp-ai-guard-status" style="color: var(--lg-primary); font-weight: 600;"><?php _e( 'Admin', 'wp-ai-guard' ); ?></a>
	</nav>

	<header class="lg-section">
		<h1 class="lg-hero-title">WP-AI-Guard</h1>
		<h2 class="lg-hero-subtitle"><?php _e( 'La seguretat de WordPress, reimaginada amb Intel·ligència Artificial.', 'wp-ai-guard' ); ?></h2>
		<p class="lg-hero-desc"><?php _e( 'Protegeix el teu lloc web en temps real contra injeccions SQL, XSS i atacs de força bruta utilitzant el poder de Google Gemini i Ollama local.', 'wp-ai-guard' ); ?></p>
		<div style="display: flex; justify-content: center; gap: 20px;">
			<a href="#features" class="lg-btn"><?php _e( 'Descobrir ara', 'wp-ai-guard' ); ?></a>
		</div>
	</header>

	<section id="features" class="lg-section">
		<div class="lg-grid">
			<div class="lg-glass-card">
				<span class="lg-card-icon dashicons dashicons-shield"></span>
				<h3 class="lg-card-title"><?php _e( 'Monitoratge Avançat', 'wp-ai-guard' ); ?></h3>
				<p class="lg-card-text"><?php _e( 'Inspecció profunda de cada petició HTTP per detectar patrons maliciosos abans que arribin al servidor.', 'wp-ai-guard' ); ?></p>
			</div>
			<div class="lg-glass-card">
				<span class="lg-card-icon dashicons dashicons-brainstorming"></span>
				<h3 class="lg-card-title"><?php _e( 'Anàlisi Neuronal', 'wp-ai-guard' ); ?></h3>
				<p class="lg-card-text"><?php _e( 'Connectivitat amb models de llenguatge (LLM) per analitzar el context dels atacs i rebre recomanacions precises.', 'wp-ai-guard' ); ?></p>
			</div>
			<div class="lg-glass-card">
				<span class="lg-card-icon dashicons dashicons-lock"></span>
				<h3 class="lg-card-title"><?php _e( 'Bloqueig Adaptatiu', 'wp-ai-guard' ); ?></h3>
				<p class="lg-card-text"><?php _e( 'Sistema de bloqueig automàtic d\'IPs basat en el risc, amb memòria cau de Transients per a un rendiment SaaS.', 'wp-ai-guard' ); ?></p>
			</div>
		</div>
	</section>

	<section id="monitor" class="lg-section">
		<div class="lg-glass-card" style="text-align: center; background: rgba(0,0,0,0.4);">
			<h2 class="lg-card-title" style="font-size: 36px; text-align: center;"><?php _e( 'Tauler de Control de Pròxima Generació', 'wp-ai-guard' ); ?></h2>
			<p class="lg-card-text" style="margin-bottom: 50px; text-align: center; max-width: 700px; margin-left: auto; margin-right: auto;">
				<?php _e( 'Gestiona la teva seguretat amb una interfície moderna, ràpida i totalment adaptada a qualsevol dispositiu.', 'wp-ai-guard' ); ?>
			</p>
			<div id="monitor-preview">
				<img src="/wordpress/wp-content/plugins/wp-ai-guard/admin/screenshot.png" alt="WP-AI-Guard Dashboard">
			</div>
		</div>
	</section>

	<footer class="lg-section" style="padding: 60px 20px; border-top: 1px solid rgba(255,255,255,0.1); opacity: 0.5;">
		<p>&copy; <?php echo date('Y'); ?> WP-AI-Guard. <?php _e( 'Seguretat avançada impulsada per IA.', 'wp-ai-guard' ); ?></p>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>
