/**
 * Tailwind só para as telas do plugin:
 * - "important: '.cflow-app'" prefixa as utilities com .cflow-app, o que as
 *   faz vencer os estilos do wp-admin sem vazar para outras telas;
 * - preflight desligado para não resetar o resto do painel do WordPress.
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./comment-flow/admin/**/*.php', './comment-flow-hub/admin/**/*.php'],
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
