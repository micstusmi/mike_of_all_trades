#!/usr/bin/env python3
"""Install only /gallery/index.php on the existing Mike of All Trades website."""
import argparse,contextlib,fcntl,hashlib,json,os,re,stat,subprocess,tempfile,urllib.request
from pathlib import Path
DOMAIN='mikeofalltrades.com.au'
INDEX_SHA='40ab99d844508f0d48f358285b56baed5fb69ff827825f2c886139cc70d37cac'

def need(ok,message):
    if not ok:raise RuntimeError(message)
def run(args):return subprocess.check_output(args,stderr=subprocess.PIPE,timeout=40)
def digest(data):return hashlib.sha256(data).hexdigest()
def atomic(path,data,mode=0o644):
    fd,name=tempfile.mkstemp(prefix='.gallery-',dir=path.parent)
    try:
        os.fchmod(fd,mode)
        with os.fdopen(fd,'wb') as out:out.write(data);out.flush();os.fsync(out.fileno())
        os.replace(name,path)
    finally:
        if os.path.exists(name):os.unlink(name)
def document_root():
    matches=set()
    for link in Path('/etc/apache2/sites-enabled').glob('*.conf'):
        text=link.resolve().read_text()
        for block in re.findall(r'<VirtualHost\b[^>]*>[\s\S]*?</VirtualHost>',text,re.I):
            names=[]
            for line in re.findall(r'^\s*(?:ServerName|ServerAlias)\s+(.+)$',block,re.M|re.I):names.extend(line.split())
            if DOMAIN not in names and 'www.'+DOMAIN not in names:continue
            roots=re.findall(r'^\s*DocumentRoot\s+"?([^"\s]+)',block,re.M|re.I)
            need(len(roots)==1,'Expected one DocumentRoot for the website host.');matches.add(str(Path(roots[0]).resolve()))
    need(len(matches)==1,'Use the server/container hosting the Mike of All Trades Apache website; its named host was not uniquely found.')
    return Path(matches.pop())
def frame_allowed(headers):
    for policy in re.findall(r'^Content-Security-Policy:\s*(.+)$',headers,re.M|re.I):
        directives={parts[0].lower():parts[1:] for item in policy.split(';') if (parts:=item.split())}
        sources=directives.get('frame-src',directives.get('child-src',directives.get('default-src')))
        if sources is not None:need(any(x in sources for x in ['*','https:','https://eztradie.com.au']),'The existing website policy does not allow the gallery origin; no policy was weakened.')
def install(commit):
    need(os.geteuid()==0 and re.fullmatch('[a-f0-9]{40}',commit),'Root and exact website source commit required.')
    root=document_root();need(root.is_dir(),'Website root missing.')
    guarded=[root/'includes'/name for name in ['header.php','sidebar.php','footer.php']]
    need(all(p.is_file() and p.resolve().is_relative_to(root) and not p.is_symlink() for p in guarded),'Original website templates are required.')
    guarded += [root/name for name in ['index.php','index.html','.htaccess'] if (root/name).is_file()]
    original_hashes={str(p):digest(p.read_bytes()) for p in guarded}
    home=run(['curl','-fsS','--max-time','25','https://'+DOMAIN+'/'])
    frame_allowed(run(['curl','-fsSI','--max-time','25','https://'+DOMAIN+'/']).decode())
    endpoint=run(['curl','-fsS','--max-time','25','https://eztradie.com.au/gallery-embed.php?inbox=mike-of-all-trades&view=slideshow'])
    need(endpoint.count(b'data-gallery-embed')==1,'Install the ezBusiness gallery endpoint first.')
    url='https://raw.githubusercontent.com/micstusmi/mike_of_all_trades/'+commit+'/gallery/index.php'
    with urllib.request.urlopen(url,timeout=30) as response:data=response.read(256*1024+1)
    need(len(data)<=256*1024 and digest(data)==INDEX_SHA,'Website source checksum differs.')
    with tempfile.TemporaryDirectory(prefix='moat-gallery-lint-',dir='/root') as directory:
        test=Path(directory)/'index.php';test.write_bytes(data);run(['php','-l',str(test)])
    with contextlib.ExitStack() as stack:
        for name in ['/run/lock/ezbusiness-deployment.lock','/var/lib/eztradie-deploy/deploy.lock']:
            if name.startswith('/var/lib') and not Path(name).parent.exists():continue
            fd=os.open(name,os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600);handle=stack.enter_context(os.fdopen(fd,'r+'));info=os.fstat(fd)
            need(stat.S_ISREG(info.st_mode) and info.st_uid==0 and info.st_nlink==1,'Unexpected deployment lock.');fcntl.flock(handle,fcntl.LOCK_EX|fcntl.LOCK_NB)
        folder=root/'gallery';need(not folder.is_symlink(),'Unexpected gallery directory link.');created=not folder.exists()
        if not created:need(folder.is_dir() and {p.name for p in folder.iterdir()}<={'index.php'},'Existing unrelated gallery files; left unchanged.')
        previous=None
        if (folder/'index.php').exists():
            index=folder/'index.php';need(not index.is_symlink() and index.stat().st_nlink==1,'Unexpected gallery file.')
            previous=index.read_bytes();need(digest(previous)==INDEX_SHA,'An unrelated gallery page already exists; left unchanged.')
        need(original_hashes=={str(p):digest(p.read_bytes()) for p in guarded},'Website source changed during preparation.')
        backup=Path('/var/backups/moat-gallery')/commit;backup.mkdir(parents=True,exist_ok=True,mode=0o700)
        before={'commit':commit,'document_root':str(root),'templates':original_hashes,'previous_hex':previous.hex() if previous is not None else None}
        if not (backup/'before.json').exists():atomic(backup/'before.json',json.dumps(before,sort_keys=True).encode(),0o600)
        if created:folder.mkdir(mode=0o755)
        try:
            atomic(folder/'index.php',data)
            page=run(['curl','-fsS','--max-time','25','https://'+DOMAIN+'/gallery/'])
            need(b'moat-gallery-demo' in page and page.count(b'<iframe ')==5,'Gallery page did not render the four examples and builder.')
            need(b'<?php' not in page,'PHP source was not executed.')
            # The original header includes the sidebar; include_once avoids duplicating it.
            need(page.count(b'class="sidebar shadow-sm"')==1 and b'footer-section' in page,'Original sidebar/footer did not render once.')
            need(b'navbar' in page,'Original header navigation missing.')
            frame_allowed(run(['curl','-fsSI','--max-time','25','https://'+DOMAIN+'/gallery/']).decode())
            run(['curl','-fsS','--max-time','25','https://'+DOMAIN+'/'])
            need(original_hashes=={str(p):digest(p.read_bytes()) for p in guarded},'Original templates, homepage or rewrite source changed.')
            combined=run(['curl','-fsS','--max-time','25','https://'+DOMAIN+'/gallery/?view=carousel&order=shuffle'])
            need(b'view=carousel&amp;order=shuffle' in combined,'Independent embed builder did not render.')
        except BaseException:
            if previous is None:(folder/'index.php').unlink(missing_ok=True)
            else:atomic(folder/'index.php',previous)
            if created and not list(folder.iterdir()):folder.rmdir()
            raise
        atomic(backup/'installed.json',json.dumps({'commit':commit,'index_sha256':INDEX_SHA,'original_sources_preserved':True},sort_keys=True).encode(),0o600)
        print('LIVE: https://'+DOMAIN+'/gallery/')
        print('PASS: four examples, independent embed builder, original header/sidebar/footer/homepage/rewrite sources preserved.')
        print('BACKUP:',backup)
if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--commit',required=True);args=parser.parse_args()
    try:install(args.commit)
    except Exception as exc:print('STOP:',str(exc) if isinstance(exc,RuntimeError) else type(exc).__name__);raise SystemExit(1)
