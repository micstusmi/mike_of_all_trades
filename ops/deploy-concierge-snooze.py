#!/usr/bin/env python3
"""Only patch the floating concierge in the existing Micstusmi website header."""
import argparse, base64, fcntl, hashlib, json, os, re, socket, stat, subprocess, tempfile, zlib
from pathlib import Path

PATCH_DATA = 'eNrNV21vIjcQ/t5fMdkPt0BhgVzUD8kRibtQCd0VopJclZYqMrsGLIy9WnvhuCi/oz+of6zjNS/7RpLm2qqWSMCeeWY8M348/u3BkTxwzh1IjYEEPSdioerQdzkHLuUCSCRjEYzFTuhdcxJrLcWlU3cEXX8TxFikJu0csKAzdpZSNwhrKCHlVzp2QG9CitNWZOwctHbD50QpI6EF4KfBmVjAutFutUDTL7qhqC9FQKJNmbLSG27gp1KgJPtKz9tn4ZeLJRONOWWzuT4/w99j5zKreiWFq0HN5RqmMoK3LQjIRpVucz8VZvYXRnRKIyp82lCa6FjhVnc7UUuC8cs6D8tJo5XbQSQT3/f6cxYEVFy+a4YmQz5GXjvn7cd6OuEIpzRcD69vr++vep+6d9ABDFWrdZFJ6nEx64FdHw2Gw1979x97ZtnFnXXZtQzjcJQk71Zoxt0yhavu3Qg13ho0u9xsQhcmXPoLGmBSZERmFLrXfVjGqCboikYwiShZYI1RA+YzGs2oq0DICMNlpjTGQ3kWbxoLXzOsKUWVwv8/UxJUFnRThYdDCDWG9QEiquNoLziytr0Z1X1Nl4nOBTyCT7Q/h8p99aAhYs5xyeI9ltv9JWKaGpA6rAiPaYn9nGF1MLzTydtv1uAzU0yDFHzjQa15zAmmbCaCStFutqBtemKTMUzMIF5OaFTBdBCeD8gh59XqRRZkGxer7TH1IxNm9wlqFd682eJfwhXR1BNyXUkjlMZ4SriihyA/U9ZC6oFcv7ds0oFA+vGSCm1873Fqvr7f9IOKuz2DKI6ftVstq/1XY6Xr3bLYi0GseA7jwBSj5KC/AKdALnaLxdAdqb30UUaOE24d3LZb3UcpU9zHhZHFnHMdxfR1pq+YWjKlaPBS8yUKRRdOj7owe8qFgvWEUY6KfqNdG8hnbR7E/o69XaiLt6EZxXQekWu7xYUjOfqXLD1ZX8mtUtsq1qDzmrHX7o4+Qrf/z4A1M2TDplBJs0SGqNMLHgmC3gpP+iemNBVIz67Pmb/AWt/zfZbmj1B9ke5HOmJiVjmwMnyfualrcHqGf35o7f6YTiBP/mZkLgxVuDDq1mKZpgnDc9cNnHQ6WwTsAiLsvTCM0IsiibEYSGRbsjLHLwefuVfKLecptlTQjLygZ1sujKG9qF6oZVq7D9iwYDZRdezcydi0N3KtsM3BUuZB0l7idnCjTIE/l8ynHtwqCn/+AVpiqyjxu+k9MWHe2Cmx/FicspdqPj7Zny8l1xxK4lAiWMkv2VbYm+KlpbI3/u7H474N/L8e2HJ+MbVzEuwCYzqcE8KxSw02CaFhGRXO+hPiyUSqZbPq5ZbT4wRLIAHIhj1vvVQFbRbLJO1EGWbRpe7tzfCn7k3/A5y2YNT7MBxcQX/wuX+Dc8PB3o2DWLtc7Mhma7vD0dBksqtQiAi+BaLk0YmVHVKkr4BvgIT41RDaPvtE2VcDPiXkEjWWckUVTKheUzy7IdKN8rKh+g8M4ntH0ZAgJrW8maKJPZCKQ5zFalFAYsQimvnAxIohj6BHKv36BOJHUilYmafB7h2EGDfoSUjxkSQM3XTVAg/G9kwC4WujGdElYYhGVoRxMuHUe77waoAPkCA2+042i4m3j9V8LI8pBJQTfOIw4fM4MJMM/cE2OgkxmWRc+P27vwCkTv3s'

def need(value, message):
    if not value: raise RuntimeError(message)

def digest(data): return hashlib.sha256(data).hexdigest()

def patched_header(source):
    result = source
    for pair in json.loads(zlib.decompress(base64.b64decode(PATCH_DATA))):
        if pair['new'] in result: continue
        count = result.count(pair['old'])
        need(count == pair['count'], 'Existing concierge differs; no file was changed. Review the header source.')
        result = result.replace(pair['old'], pair['new'])
    return result

def website_header():
    roots = set()
    for config in Path('/etc/apache2/sites-enabled').glob('*.conf'):
        text = config.read_text()
        for host in re.findall(r'<VirtualHost\b[^>]*:443[^>]*>[\s\S]*?</VirtualHost>', text, re.I):
            if not re.search(r'^\s*ServerName\s+(?:www\.)?mikeofalltrades\.com\.au\s*$', host, re.M | re.I): continue
            matches = re.findall(r'^\s*DocumentRoot\s+(?:"([^"]+)"|(\S+))\s*$', host, re.M | re.I)
            need(len(matches) == 1, 'Expected one DocumentRoot for the existing site.')
            roots.add(Path(matches[0][0] or matches[0][1]).resolve())
    need(len(roots) == 1, 'Cannot identify one existing Mike of All Trades website.')
    root = roots.pop(); header = root/'includes/header.php'
    need(header.is_file() and not header.is_symlink() and header.resolve().is_relative_to(root), 'Unexpected website header path.')
    return header

def atomic(path, data, metadata):
    need(not path.is_symlink(), 'Unexpected header symlink.')
    fd, name = tempfile.mkstemp(prefix='.concierge-', suffix='.php', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream: stream.write(data)
        os.chmod(name, stat.S_IMODE(metadata.st_mode)); os.chown(name, metadata.st_uid, metadata.st_gid)
        subprocess.run(['php', '-l', name], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, timeout=20)
        os.replace(name, path)
    finally:
        if os.path.exists(name): os.unlink(name)

def smoke():
    body = subprocess.check_output(['curl', '-fsS', '--max-time', '25', '--resolve', 'mikeofalltrades.com.au:443:127.0.0.1', 'https://mikeofalltrades.com.au/?concierge-release=30-days'], timeout=30)
    need(b'mot-ai-snooze' in body and b'motAiPopupSnoozeUntil' in body, 'New concierge not served; header restored.')

def main(target):
    need(os.geteuid() == 0 and socket.gethostname().split('.')[0] == 'mikeofalltrades', 'Use the Micstusmi / mikeofalltrades instance as root.')
    need(re.fullmatch(r'[a-f0-9]{40}', target), 'Exact reviewed source commit required.')
    with open('/var/lock/ezbusiness-deployment.lock', 'a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        header = website_header(); before = header.read_bytes(); metadata = header.stat()
        after = patched_header(before.decode()).encode()
        folder = Path('/var/backups/mikeofalltrades')/('concierge-'+target)
        folder.mkdir(mode=0o700, parents=True, exist_ok=True); folder.chmod(0o700)
        backup = folder/'header.before.php'
        if after != before:
            need(not backup.is_symlink(), 'Unexpected backup symlink.')
            if backup.exists():
                need(backup.read_bytes() == before, 'A different prior header backup exists; review it before retry.')
            else:
                with backup.open('xb') as stream: stream.write(before)
                backup.chmod(0o600)
            need(header.read_bytes() == before, 'Header changed during preparation; nothing overwritten.')
            atomic(header, after, metadata)
        try: smoke()
        except Exception:
            if after != before:
                need(header.read_bytes() == after, 'Header changed after deployment; preserve current file and inspect backup.')
                atomic(header, before, metadata)
            raise
        print('PASS: 30-day dismissal served; manual Ask AI remains available.')
        print('Source:', target); print('Header SHA256:', digest(after)); print('Backup:', backup)
        print('Only includes/header.php patched; no database, tracker, vhost or service changes.')

if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--target', required=True); args = parser.parse_args()
    try: main(args.target)
    except Exception as error:
        print('STOP:', str(error) if isinstance(error, RuntimeError) else type(error).__name__)
        raise SystemExit(1)
