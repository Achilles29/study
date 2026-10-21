import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
import fs from 'node:fs';
const base = process.env.SEO_AUDIT_BASE || 'http://127.0.0.1:8097';
if (!['http://127.0.0.1:8097','https://www.projectpintar.com'].includes(base)) throw new Error('Unexpected target');
const browser = await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
const results=[];
try {
	for (const route of ['/', '/kursus']) {
		for (let run=1;run<=3;run++) {
			const context=await browser.createBrowserContext(), page=await context.newPage();
			await page.setViewport({width:360,height:800,isMobile:true,deviceScaleFactor:1});
			await page.setUserAgent('ProjectPintar-QA/SEO074-Performance');
			const cdp=await page.createCDPSession();
			await cdp.send('Network.enable');
			await cdp.send('Network.setCacheDisabled',{cacheDisabled:true});
			await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:150,downloadThroughput:200000,uploadThroughput:93750});
			await cdp.send('Emulation.setCPUThrottlingRate',{rate:4});
			await page.evaluateOnNewDocument(()=>{
				window.lab={lcp:0,cls:0,longTasks:0};
				new PerformanceObserver(l=>l.getEntries().forEach(e=>{window.lab.lcp=e.startTime;})).observe({type:'largest-contentful-paint',buffered:true});
				new PerformanceObserver(l=>l.getEntries().forEach(e=>{if(!e.hadRecentInput)window.lab.cls+=e.value;})).observe({type:'layout-shift',buffered:true});
				new PerformanceObserver(l=>l.getEntries().forEach(e=>{window.lab.longTasks+=Math.max(0,e.duration-50);})).observe({type:'longtask',buffered:true});
			});
			await page.goto(base+route,{waitUntil:'networkidle0'});
			const result=await page.evaluate(()=>{
				const nav=performance.getEntriesByType('navigation')[0], resources=performance.getEntriesByType('resource');
				return {...window.lab,ttfb:nav.responseStart-nav.requestStart,fcp:performance.getEntriesByName('first-contentful-paint')[0]?.startTime,requests:resources.length+1,bytes:nav.encodedBodySize+resources.reduce((s,r)=>s+r.encodedBodySize,0),overflow:document.documentElement.scrollWidth>innerWidth,resources:resources.map(r=>({path:new URL(r.name).pathname,bytes:r.encodedBodySize}))};
			});
			results.push({route,run,...result});await context.close();
		}
	}
	console.log(JSON.stringify({base,note:'Lab only: 360px, cold cache, 150ms RTT simulation, 1.6Mbps, 4x CPU. Not field CWV or INP.',results},null,2));
	if(process.env.SEO_AUDIT_OUTPUT?.startsWith('/www/backup/projectpintar-seo074-csJgiDq1/')) fs.writeFileSync(process.env.SEO_AUDIT_OUTPUT,JSON.stringify({base,results},null,2));
} finally {await browser.close();}
