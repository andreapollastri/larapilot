// Screenshots of the Fernway dashboard in headless Chrome over the DevTools protocol (Node 24: global WebSocket).
//
//   node shots.mjs <jobs.json> <out-dir> [--only=name,name] [--local=http://127.0.0.1:8841] [--staging=http://127.0.0.1:8842]
//                  [--login=user:password] [--chrome=/path/to/chrome] [--format=webp|png]
//
// A job: { "name": "board", "path": "/larapilot", "server": "local"|"staging"|"none", "width": 1440, "height": 900,
//          "scale": 1.5, "dark": false, "mobile": false, "eval": "js run after load", "wait": 400,
//          "signedOut": false, "url": "absolute url instead of server+path", "follow": "js returning a url to open next" }
// Desktop 1440x900 at scale 1.5 gives the 2160x1350 images of the docs; a phone is 390x844 at scale 2.
// The staging jobs sign in once, through the form inside Chrome (a cookie copied from curl did not authenticate).
// Each job prints whether the page scrolls sideways, its console errors, and its failed requests.
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { tmpdir } from 'node:os';
import http from 'node:http';

const args = process.argv.slice(2);
const opts = Object.fromEntries(args.filter(a => a.startsWith('--')).map(a => { const i = a.indexOf('='); return i < 0 ? [a.slice(2), 'true'] : [a.slice(2, i), a.slice(i + 1)]; }));
const [jobsFile, outDir] = args.filter(a => !a.startsWith('--'));
if (!jobsFile || !outDir) { console.error('usage: node shots.mjs <jobs.json> <out-dir> [--only=a,b]'); process.exit(2); }
// Chrome asks for http://fernway.test/... and http://staging.fernway.test/...; every request is answered from the
// local servers with that Host header, so the links Laravel draws (the curl example, the public roadmap link)
// read fernway.test and not 127.0.0.1:port.
const upstream = { 'fernway.test': opts.local ?? 'http://127.0.0.1:8841', 'staging.fernway.test': opts.staging ?? 'http://127.0.0.1:8842' };
const servers = { local: 'http://fernway.test', staging: 'http://staging.fernway.test' };
const only = opts.only ? new Set(opts.only.split(',')) : null;
const format = opts.format ?? 'webp';
// __KIT__ in a job's url stands for the folder of this script (og-card.json opens og-card.html).
const kit = new URL('.', import.meta.url).pathname.replace(/\/$/, '');
let jobs = JSON.parse(readFileSync(jobsFile, 'utf8').replaceAll('__KIT__', kit));
if (only) jobs = jobs.filter(j => only.has(j.name));
mkdirSync(outDir, { recursive: true });

const chrome = opts.chrome ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const port = Number(opts.port ?? (9300 + Math.floor(Math.random() * 600)));
const profile = resolve(tmpdir(), `fernway-shots-${port}`);
const proc = spawn(chrome, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run',
  '--no-default-browser-check', '--hide-scrollbars', '--force-color-profile=srgb', '--font-render-hinting=none', 'about:blank'], { stdio: 'ignore' });

const sleep = ms => new Promise(r => setTimeout(r, ms));
async function json(path) { for (let i = 0; i < 80; i++) { try { const r = await fetch(`http://127.0.0.1:${port}${path}`); return await r.json(); } catch { await sleep(150); } } throw new Error('chrome did not start'); }

const page = (await json('/json/list')).find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r, { once: true }));
let seq = 0; const pending = new Map(); const events = [];
ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } else if (m.method) events.push(m); });
const send = (method, params = {}) => new Promise(r => { const i = ++seq; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
const evaluate = async (expression) => (await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result?.result?.value;
const loaded = (timeout = 15000) => new Promise(r => { const t = setInterval(() => { if (events.some(e => e.method === 'Page.loadEventFired')) { clearInterval(t); r(); } }, 50); setTimeout(() => { clearInterval(t); r(); }, timeout); });
async function go(url) { events.length = 0; await send('Page.navigate', { url }); await loaded(); }

await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable'); await send('Log.enable');
await send('Fetch.enable', { patterns: Object.keys(upstream).map(host => ({ urlPattern: `http://${host}/*`, requestStage: 'Request' })) });
function proxy(params) {
  const url = new URL(params.request.url);
  const target = new URL(upstream[url.hostname]);
  const headers = { ...params.request.headers, Host: url.hostname };
  const body = params.request.postData ?? (params.request.postDataEntries ?? []).map(e => Buffer.from(e.bytes ?? '', 'base64').toString()).join('');
  if (body && params.request.method !== 'GET') headers['Content-Length'] = Buffer.byteLength(body);
  const req = http.request({ host: target.hostname, port: target.port, path: url.pathname + url.search, method: params.request.method, headers }, res => {
    const chunks = []; res.on('data', c => chunks.push(c));
    res.on('end', () => {
      const responseHeaders = [];
      for (const [name, value] of Object.entries(res.headers)) if (name !== 'transfer-encoding') for (const v of [].concat(value)) responseHeaders.push({ name, value: String(v) });
      ws.send(JSON.stringify({ id: ++seq, method: 'Fetch.fulfillRequest', params: { requestId: params.requestId, responseCode: res.statusCode, responseHeaders, body: Buffer.concat(chunks).toString('base64') } }));
    });
  });
  req.on('error', e => ws.send(JSON.stringify({ id: ++seq, method: 'Fetch.failRequest', params: { requestId: params.requestId, errorReason: 'ConnectionRefused' } })));
  if (body && params.request.method !== 'GET') req.write(body);
  req.end();
}
ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (m.method === 'Fetch.requestPaused') proxy(m.params); });

let signedIn = false;
async function signIn() {
  const [user, ...rest] = (opts.login ?? '').split(':');
  if (!user) throw new Error('staging jobs need --login=user:password');
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
  await go(`${servers.staging}/larapilot/login`);
  events.length = 0;
  await evaluate(`(() => { const f = document.querySelector('form'); f.username.value = ${JSON.stringify(user)}; f.password.value = ${JSON.stringify(rest.join(':'))}; f.submit(); })()`);
  await loaded();
  console.error('signed in, now at', await evaluate('location.href'));
  signedIn = true;
}

const report = [];
for (const job of jobs) {
  const server = job.server ?? 'local';
  if (server === 'staging' && !job.signedOut && !signedIn) await signIn();
  const width = job.width ?? (job.mobile ? 390 : 1440);
  const height = job.height ?? (job.mobile ? 844 : 900);
  const scale = job.scale ?? (job.mobile ? 2 : 1.5);
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: scale, mobile: !!job.mobile, screenWidth: width, screenHeight: height });
  await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: job.dark ? 'dark' : 'light' }, { name: 'prefers-reduced-motion', value: 'reduce' }] });
  const url = job.url ?? `${servers[server]}${job.path}`;
  await go(url);
  if (job.follow) {
    // A page reached from another one (a mail of the list, the public link of the roadmap).
    const next = await evaluate(job.follow);
    if (!next) { report.push({ name: job.name, error: 'follow found nothing' }); continue; }
    await go(next);
  }
  await sleep(job.wait ?? 500);
  if (job.eval) { const value = await evaluate(job.eval); if (value !== undefined) console.error(job.name, 'eval →', value); await sleep(job.waitAfterEval ?? 400); }
  const m = JSON.parse(await evaluate('JSON.stringify({sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, title: document.title, href: location.href})'));
  const shot = await send('Page.captureScreenshot', { format, quality: format === 'png' ? undefined : (job.quality ?? 86), captureBeyondViewport: false });
  const file = resolve(outDir, `${job.out ?? job.name}.${format}`);
  writeFileSync(file, Buffer.from(shot.result.data, 'base64'));
  const errors = events.filter(e => e.method === 'Runtime.exceptionThrown' || (e.method === 'Log.entryAdded' && e.params.entry.level === 'error')).map(e => e.params.exceptionDetails?.exception?.description ?? e.params.exceptionDetails?.text ?? e.params.entry?.text);
  const failed = events.filter(e => e.method === 'Network.loadingFailed' && e.params.errorText !== 'net::ERR_ABORTED').map(e => e.params.errorText);
  const status = events.filter(e => e.method === 'Network.responseReceived' && e.params.type === 'Document').map(e => e.params.response.status);
  report.push({ name: job.name, file, at: m.href, title: m.title, status, overflowX: m.sw > m.cw, errors, failed });
}
console.log(JSON.stringify(report, null, 1));
ws.close(); proc.kill('SIGKILL'); await sleep(300); try { rmSync(profile, { recursive: true, force: true }); } catch {}
process.exit(0);
