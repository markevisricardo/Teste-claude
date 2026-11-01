/** Tailwind da landing page da Dra. Lidiane (separado do plugin Comment Flow). */
/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./landing-dra-lidiane/index.html'],
	theme: {
		extend: {
			colors: {
				cream: '#F7F3EF',
				nude: { 100: '#F4ECE6', 200: '#EEDFD5', 300: '#E9D3C6' },
				rose: { 400: '#D4AA90', 500: '#C99A7E', 600: '#B4826A' },
				cocoa: { 500: '#8C6553', 700: '#6B4A3A', 900: '#3E2A22' },
				night: { 800: '#1E1817', 900: '#141011' },
			},
			fontFamily: {
				serif: ['"Gilda Display"', 'Georgia', 'serif'],
				sans: ['Montserrat', 'system-ui', 'sans-serif'],
			},
			boxShadow: {
				soft: '0 10px 30px -12px rgba(107, 74, 58, .25)',
			},
		},
	},
	plugins: [],
};
