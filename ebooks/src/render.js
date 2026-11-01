const { chromium } = require('playwright');
(async () => {
  const items = JSON.parse(process.argv[2]);
  const browser = await chromium.launch();
  const page = await browser.newPage();
  for (const it of items) {
    await page.goto('file://' + it.html, { waitUntil: 'load' });
    await page.pdf({
      path: it.pdf, format: 'A4', printBackground: true, preferCSSPageSize: true,
      displayHeaderFooter: true, headerTemplate: '<div></div>',
      footerTemplate: `<div style="width:100%;font-family:Inter,sans-serif;font-size:7px;color:#8a93a3;padding:0 17mm;display:flex;justify-content:space-between;"><span>Coleção Acelerador de Clínicas · Vol. ${String(it.vol).padStart(2,'0')} — ${it.titulo}</span><span><b style="color:#0B1B33">aceleradordeclinicas.com.br</b> · <span class="pageNumber"></span></span></div>`,
    });
    console.log('ok', it.pdf);
  }
  await browser.close();
})();
