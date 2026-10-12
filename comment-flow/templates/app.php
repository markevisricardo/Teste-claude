<?php
/**
 * Modelo de tela cheia da página do shortcode (sem o tema do site).
 */

defined( 'ABSPATH' ) || exit;

$cflow_body = CFlow_Frontend::render_page();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#000000">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', CFLOW_VERSION, CFlow_Frontend::css_url() ) ); ?>">
</head>
<body class="cflow-screen">
<?php echo $cflow_body; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML montado pelas views, que escapam o conteúdo. ?>
</body>
</html>
