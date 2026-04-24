import os
import re
import sys

# Konfigurasi Warna untuk Terminal
class Colors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'

# Pola Regex untuk Pengesanan Kerentanan (Vulnerability Detection Patterns)
VULN_SIGS = [
    {
        "id": "SQLI_RAW",
        "name": "SQL Injection (Raw Query)",
        "pattern": r"mysqli_query\s*\(\s*\$conn\s*,\s*[\"'](SELECT|INSERT|UPDATE|DELETE).*?\$.*?[\"']\s*\)",
        "severity": "TINGGI",
        "desc": "Penggunaan mysqli_query dengan pembolehubah terus (concatenation) tanpa Prepared Statement."
    },
    {
        "id": "XSS_ECHO",
        "name": "Cross-Site Scripting (Reflected)",
        "pattern": r"echo\s+\$_(GET|POST|REQUEST)\[",
        "severity": "SEDERHANA",
        "desc": "Mengeluarkan input pengguna terus ke skrin tanpa sanitasi (htmlspecialchars)."
    },
    {
        "id": "HARDCODED_CREDS",
        "name": "Hardcoded Credentials",
        "pattern": r"(password|passwd|pwd)\s*=\s*[\"'][^\"'\s]+[\"'];",
        "severity": "TINGGI",
        "desc": "Kata laluan dikesan dalam kod sumber. Sepatutnya menggunakan fail konfigurasi persekitaran (.env)."
    },
    {
        "id": "AUTH_BYPASS",
        "name": "Potential Auth Bypass (Google)",
        "pattern": r"json_decode.*email.*session_start",
        "severity": "KRITIKAL",
        "desc": "Logik pengesahan yang mungkin mempercayai input pengguna tanpa verifikasi token pelayan (Server-side Token Validation)."
    },
    {
        "id": "FILE_UPLOAD",
        "name": "Unsafe File Upload",
        "pattern": r"move_uploaded_file",
        "severity": "SEDERHANA",
        "desc": "Fungsi muat naik fail dikesan. Perlu semakan manual untuk memastikan jenis fail (MIME type) diperiksa."
    }
]

def scan_file(filepath):
    findings = []
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
            
            # Semakan Khas untuk google_auth.php
            if "google_auth.php" in filepath:
                if "verifyIdToken" not in content and not ("tokeninfo" in content and "curl_init" in content) and "Client" in content and "email" in content:
                     findings.append({
                        "id": "AUTH_BYPASS_CONFIRMED",
                        "name": "Google Auth Token Verification Missing",
                        "severity": "KRITIKAL",
                        "line": "N/A",
                        "match": "verifyIdToken() function not found",
                        "desc": "Fail ini menerima email dari client tetapi TIDAK mengesahkan token Google di server. Sesiapa sahaja boleh login sebagai sesiapa."
                    })

            for sig in VULN_SIGS:
                matches = re.finditer(sig['pattern'], content, re.IGNORECASE | re.MULTILINE)
                for match in matches:
                    # Special Check: Skip File Upload finding if MIME check is present
                    if sig['id'] == 'FILE_UPLOAD' and ("finfo_open" in content or "finfo_file" in content):
                        continue

                    # Dapatkan nombor baris
                    line_num = content[:match.start()].count('\n') + 1
                    match_text = match.group(0).strip()[:50] + "..."
                    
                    findings.append({
                        "id": sig['id'],
                        "name": sig['name'],
                        "severity": sig['severity'],
                        "line": line_num,
                        "match": match_text,
                        "desc": sig['desc']
                    })
    except Exception as e:
        print(f"Ralat membaca fail {filepath}: {str(e)}")
    
    return findings

def main():
    target_dir = os.getcwd() # Scan current directory
    print(f"{Colors.HEADER}=================================================={Colors.ENDC}")
    print(f"{Colors.HEADER}    MYPEKEMA AUTOMATED PENETRATION TESTER        {Colors.ENDC}")
    print(f"{Colors.HEADER}=================================================={Colors.ENDC}")
    print(f"Target Directory: {target_dir}\n")

    total_findings = 0
    files_scanned = 0
    critical_count = 0

    # Scan semua fail PHP
    for root, dirs, files in os.walk(target_dir):
        # Skip folder tertentu
        if 'vendor' in dirs:
            dirs.remove('vendor')
        if '.git' in dirs:
            dirs.remove('.git')
            
        for file in files:
            if file.endswith('.php'):
                files_scanned += 1
                filepath = os.path.join(root, file)
                rel_path = os.path.relpath(filepath, target_dir)
                
                findings = scan_file(filepath)
                
                if findings:
                    print(f"{Colors.BOLD}FILE: {rel_path}{Colors.ENDC}")
                    for f in findings:
                        total_findings += 1
                        color = Colors.OKBLUE
                        if f['severity'] == 'TINGGI': color = Colors.WARNING
                        if f['severity'] == 'KRITIKAL': 
                            color = Colors.FAIL
                            critical_count += 1
                        
                        print(f"  [{color}{f['severity']}{Colors.ENDC}] {f['name']}")
                        print(f"    Line: {f['line']}")
                        print(f"    Code: {f['match']}")
                        print(f"    Note: {f['desc']}")
                        print("-" * 40)
                    print("\n")

    print(f"{Colors.HEADER}=================================================={Colors.ENDC}")
    print(f"SCAN COMPLETE")
    print(f"Files Scanned: {files_scanned}")
    print(f"Total Issues Found: {total_findings}")
    print(f"Critical Issues: {critical_count}")
    
    if critical_count > 0:
        print(f"\n{Colors.FAIL}!!! CRITICAL VULNERABILITIES DETECTED !!!{Colors.ENDC}")
        print("Sistem ini TIDAK SELAMAT untuk production sehingga isu Kritikal diselesaikan.")

if __name__ == "__main__":
    main()
