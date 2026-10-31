/**
 * Tailwind só para as telas do plugin Post Flow (painel no front-end e wp-admin):
 * - "important: '.pflow-app'" prefixa as utilities com .pflow-app, o que as
 *   faz vencer os estilos do wp-admin e do tema sem vazar para outras telas;
 * - preflight desligado para não resetar o resto do painel do WordPress.
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./post-flow/app/**/*.php', './post-flow/admin/**/*.php', './post-flow/templates/**/*.php', './post-flow/includes/**/*.php'],
	important: '.pflow-app',
	corePlugins: {
		preflight: false,
	},
	theme: {
		extend: {
			fontFamily: {
				sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
			},
		},
	},
	plugins: [],
};
