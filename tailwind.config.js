/**
 * Tailwind só para as telas do plugin (painel no front-end e wp-admin):
 * - "important: '.cflow-app'" prefixa as utilities com .cflow-app, o que as
 *   faz vencer os estilos do wp-admin e do tema sem vazar para outras telas;
 * - preflight desligado para não resetar o resto do painel do WordPress.
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./comment-flow/app/**/*.php', './comment-flow/admin/**/*.php', './comment-flow/templates/**/*.php'],
	important: '.cflow-app',
	corePlugins: {
		preflight: false,
	},
	theme: {
		extend: {
			fontFamily: {
				sans: [
					'Inter',
					'-apple-system',
					'BlinkMacSystemFont',
					'"Segoe UI"',
					'Roboto',
					'"Helvetica Neue"',
					'Arial',
					'sans-serif',
				],
			},
		},
	},
	plugins: [],
};
