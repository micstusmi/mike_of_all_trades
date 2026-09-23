<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'");
?><!doctype html>
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ezTradie alpha</title>
<link rel="stylesheet" href="/assets/app.css">
<header><h1>ezTradie <small>private alpha</small></h1><a href="/features">Feature catalogue</a></header>
<main>
  <p class="notice">This alpha uses empty business workspaces. Mike of All Trades customer records and integrations are not available here.</p>
  <p id="notice" role="status" aria-live="polite"></p>
  <section id="sign-in" hidden>
    <h2>Sign in</h2>
    <p>Your invitation includes a business ID. Registration is by invitation only.</p>
    <form id="login-form"><label>Business ID <input name="business_id" type="number" min="1" required></label><label>Email <input name="email" type="email" autocomplete="username" required></label><label>Password <input name="password" type="password" autocomplete="current-password" required></label><button>Sign in</button></form>
    <details><summary>Accept an invitation or recovery token</summary><form id="redeem-form"><label>Token <input name="token" autocomplete="off" required></label><label>Type <select name="purpose"><option value="invite">Invitation</option><option value="reset">Password recovery</option></select></label><label>New password (at least 12 characters) <input name="password" type="password" minlength="12" autocomplete="new-password" required></label><button>Set password</button></form></details>
  </section>
  <section id="workspace" hidden>
    <div class="top"><h2>Business workspace</h2><button id="logout" type="button">Sign out</button></div>
    <p id="identity"></p>
    <nav aria-label="Workspace"><button data-view="jobs">Jobs</button><button data-view="calendar">Quick booking</button><button data-view="customers">Customers</button><button data-view="sms">SMS drafts</button><button data-view="feedback">Feature requests</button><button data-view="usage">AI usage</button><button data-view="members">Members</button></nav>
    <section id="content" aria-live="polite"></section>
  </section>
</main>
<script src="/assets/app.js" defer></script>
</html>
