import hashlib, sys
REPO = sys.argv[1]
src = open(f'{REPO}/diagnostics/RD-18-WP4_owner_20261028.ps1', encoding='ascii').read()
sha = hashlib.sha256(open(f'{REPO}/patches/ACC-006-visual-WP1_20261028.php', 'rb').read()).hexdigest()
reps = [
 ("$Name='RD-18-WP4_detail-polish_20261028.php'", "$Name='ACC-006-visual-WP1_20261028.php'", 1),
 ("$Sha='6e0228de91fa957afca9dc63a908e160633c1fab401ccecdbd52e1ddf7c46e9b'", f"$Sha='{sha}'", 1),
 ("'work\\rd18-wp4-20261028\\owner-receipts'", "'work\\acc006-visual-wp1-20261028\\owner-receipts'", 1),
 ("F=$S/RD-18-WP4_detail-polish_20261028.php", "F=$S/ACC-006-visual-WP1_20261028.php", 1),
 ("RD18_WP4_CACHE_PHP", "ACC006_WP1_CACHE_PHP", 2),
 ("RD18_WP4_", "ACC006_WP1_", 5),
 ("This writes the nine admitted targets.", "This writes the sixteen admitted targets.", 1),
 ("_patch_backups/RD-18-WP4_detail-polish_20261028-", "_patch_backups/ACC-006-visual-WP1_20261028-", 2),
 ("'(?m)^changed_files=9$'", "'(?m)^changed_files=16$'", 1),
]
out = src
for old, new, n in reps:
    assert out.count(old) == n, (old, out.count(old)); out = out.replace(old, new)
assert 'RD18' not in out and 'RD-18' not in out and 'rd18' not in out
out.encode('ascii')
open(f'{REPO}/diagnostics/ACC-006-visual-WP1_owner_20261028.ps1', 'w', encoding='ascii', newline='\n').write(out)
print(hashlib.sha256(out.encode()).hexdigest())
