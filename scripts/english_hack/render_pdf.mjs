import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
import {fileURLToPath} from 'node:url';
const folder=new URL('../../application/content/english_hack/v1/',import.meta.url);
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const page=await browser.newPage();await page.setJavaScriptEnabled(false);
 await page.goto(new URL('english-hack-coursebook-v1.html',folder).href);
 await page.pdf({path:fileURLToPath(new URL('english-hack-coursebook-v1.pdf',folder)),format:'A4',printBackground:true,preferCSSPageSize:true,displayHeaderFooter:true,headerTemplate:'<span></span>',footerTemplate:'<div style="font-size:8px;width:100%;text-align:center;color:#556">Project Pintar · English Hack v1 · <span class="pageNumber"></span> / <span class="totalPages"></span></div>'});
 console.log('Coursebook PDF rendered.');
}finally{await browser.close();}
