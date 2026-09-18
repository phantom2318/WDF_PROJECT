// ============================================================
//  server.js — Tiny local dev server for the Student Portal
//
//  Purpose:
//    • Serves all static files (HTML, CSS, JS, JSON) from this folder.
//    • Exposes POST /save-users  so auth.js can persist registrations
//      back to users.json without any external internet connection.
//
//  Usage:
//    1.  node server.js          (default port 3000)
//    2.  Open http://localhost:3000/login.html in your browser.
//
//  Requirements: Node.js (any version >= 12).  No npm install needed —
//  only built-in modules (http, fs, path, url) are used.
// ============================================================

var http = require('http');
var fs   = require('fs');
var path = require('path');
var url  = require('url');

var PORT     = 3000;
var ROOT_DIR = path.join(__dirname, '..');
var USERS_FILE = path.join(ROOT_DIR, 'data', 'users.json');

// ── MIME types for static serving ────────────────────────────
var MIME = {
    '.html': 'text/html',
    '.css':  'text/css',
    '.js':   'application/javascript',
    '.json': 'application/json',
    '.png':  'image/png',
    '.jpg':  'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif':  'image/gif',
    '.ico':  'image/x-icon',
    '.pdf':  'application/pdf'
};

// ── Helper: send JSON response ────────────────────────────────
function sendJSON(res, status, obj) {
    var body = JSON.stringify(obj);
    res.writeHead(status, {
        'Content-Type':  'application/json',
        'Access-Control-Allow-Origin': '*'
    });
    res.end(body);
}

// ── Helper: read full request body ───────────────────────────
function readBody(req, cb) {
    var chunks = [];
    req.on('data', function (chunk) { chunks.push(chunk); });
    req.on('end',  function ()      { cb(Buffer.concat(chunks).toString()); });
    req.on('error', function (err)  { cb(null, err); });
}

// ── Request handler ───────────────────────────────────────────
var server = http.createServer(function (req, res) {
    var parsed  = url.parse(req.url);
    var pathname = parsed.pathname;

    // ── CORS preflight ────────────────────────────────────────
    if (req.method === 'OPTIONS') {
        res.writeHead(204, { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': 'Content-Type', 'Access-Control-Allow-Methods': 'GET,POST,OPTIONS' });
        res.end();
        return;
    }

    // ── POST /save-users — write users array back to users.json ──
    if (req.method === 'POST' && pathname === '/save-users') {
        readBody(req, function (body, err) {
            if (err) {
                return sendJSON(res, 400, { ok: false, error: 'Could not read request body.' });
            }
            var users;
            try {
                users = JSON.parse(body);
            } catch (e) {
                return sendJSON(res, 400, { ok: false, error: 'Invalid JSON.' });
            }
            if (!Array.isArray(users)) {
                return sendJSON(res, 400, { ok: false, error: 'Expected a JSON array.' });
            }
            fs.writeFile(USERS_FILE, JSON.stringify(users, null, 2), function (writeErr) {
                if (writeErr) {
                    console.error('Failed to write users.json:', writeErr.message);
                    return sendJSON(res, 500, { ok: false, error: 'Could not write users.json.' });
                }
                console.log('[server] users.json updated — ' + users.length + ' user(s).');
                sendJSON(res, 200, { ok: true });
            });
        });
        return;
    }

    // ── GET — static file serving ─────────────────────────────
    if (req.method !== 'GET') {
        sendJSON(res, 405, { ok: false, error: 'Method not allowed.' });
        return;
    }

    // Default to landing page at root
    if (pathname === '/' || pathname === '') {
        pathname = '/pages/landingPage.html';
    }

    var filePath = path.join(ROOT_DIR, pathname);

    // Prevent directory traversal outside ROOT_DIR
    if (filePath.indexOf(ROOT_DIR) !== 0) {
        sendJSON(res, 403, { ok: false, error: 'Forbidden.' });
        return;
    }

    fs.readFile(filePath, function (err, data) {
        if (err) {
            res.writeHead(404, { 'Content-Type': 'text/plain' });
            res.end('404 Not Found: ' + pathname);
            return;
        }
        var ext  = path.extname(filePath).toLowerCase();
        var mime = MIME[ext] || 'application/octet-stream';
        res.writeHead(200, { 'Content-Type': mime });
        res.end(data);
    });
});

server.listen(PORT, function () {
    console.log('');
    console.log('  Student Portal server running.');
    console.log('  Open: http://localhost:' + PORT + '/login.html');
    console.log('');
});
