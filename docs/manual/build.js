/**
 * Gera docs/Post-Flow-Manual.pdf a partir de docs/manual/manual.html.
 * Uso: node docs/manual/build.js   (precisa do Playwright com Chromium)
 */
const path = require('path');
const { chromium } = require('playwright');

(async () => {
	const src = path.join(__dirname, 'manual.html');
	const out = path.join(__dirname, '..', 'Post-Flow-Manual.pdf');
	const tmp = require('os').tmpdir();
	const browser = await chromium.launch();
	const page = await browser.newPage();

	// Capa (sem rodapé).
	await page.goto('file://' + src, { waitUntil: 'load' });
	await page.addStyleTag({ content: 'body > section:not(.cover){display:none!important}' });
	await page.pdf({ path: path.join(tmp, 'pflow-cover.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });

	// Corpo (com número de página e marcadores).
	await page.goto('file://' + src, { waitUntil: 'load' });
	await page.addStyleTag({ content: '.cover{display:none!important} @page :first { margin: 22mm 18mm 20mm 18mm; }' });
	await page.pdf({
		path: path.join(tmp, 'pflow-body.pdf'),
		format: 'A4',
		printBackground: true,
		preferCSSPageSize: true,
		displayHeaderFooter: true,
		outline: true,
		tagged: true,
		headerTemplate: '<div></div>',
		footerTemplate:
			'<div style="width:100%;font-family:Inter,Arial,sans-serif;font-size:7.5pt;color:#8a8499;padding:0 18mm;display:flex;justify-content:space-between;">' +
			'<span>Post Flow — Manual e Tutorial · v0.3.0</span><span class="pageNumber"></span></div>',
	});
	await browser.close();

	// Junta capa + corpo (pypdf mantém os links internos e os marcadores do corpo).
	require('child_process').execFileSync('python3', [path.join(__dirname, 'merge.py'), path.join(tmp, 'pflow-cover.pdf'), path.join(tmp, 'pflow-body.pdf'), out], { stdio: 'inherit' });
	console.log('PDF gerado em ' + out);
})();
