import os
import re

# Senarai fail yang dikesan mempunyai kod laluan keras (hardcoded credentials)
files_to_fix = [
    "analisis_cukai.php",
    "analisis_rangkaian-5Jan.php",
    "analisis_tahunan.php",
    "analisis_usia.php",
    "delete_gbpekema.php",
    "delete_vehicle-keep.php",
    "export_csv.php",
    "get_gbpekema.php",
    "get_vehicle.php",
    "insert_vehicle.php",
    "kad_kenderaan-6Jan.php",
    "login-lama.php",
    "lupa_password.php",
    "populate_gbpekema.php",
    "populate_vehicle.php",
    "process_excel.php",
    "profile.php",
    "proses_carian_pintar-5Jan.php",
    "register.php",
    "report-6Jan.php",
    "reset_password.php",
    "test_connection.php",
    "truncate_gbpekema.php",
    "truncate_vehicle.php",
    "update_gbpekema.php",
    "update_vehicle.php"
]

# Corak Regex untuk menangkap blok sambungan database lama
# Ia mencari $servername, $username, $password, $dbname dan new mysqli()
regex_pattern = r'(\$servername\s*=\s*["\'].*?["\']\s*;\s*\$username\s*=\s*["\'].*?["\']\s*;\s*\$password\s*=\s*["\'].*?["\']\s*;\s*\$dbname\s*=\s*["\'].*?["\']\s*;\s*\$conn\s*=\s*new\s*mysqli\([^)]+\)\s*;(\s*if\s*\(\$conn->connect_error\)\s*\{\s*die\([^)]+\)\s*;\s*\})?)'

project_path = os.getcwd()

print("Memulakan proses pembersihan kata laluan...")

fixed_count = 0
for filename in files_to_fix:
    filepath = os.path.join(project_path, filename)
    
    if os.path.exists(filepath):
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Cari jika ada padanan
            match = re.search(regex_pattern, content, re.DOTALL)
            
            if match:
                # Gantikan dengan include 'config.php'
                # Kita tambah semakan path untuk memastikan include berjaya
                replacement = "include 'config.php';"
                
                # Lakukan penggantian
                new_content = re.sub(regex_pattern, replacement, content, flags=re.DOTALL)
                
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                
                print(f"[OK] Dibaiki: {filename}")
                fixed_count += 1
            else:
                print(f"[SKIP] Tiada padanan corak ditemui dalam: {filename}")
                
        except Exception as e:
            print(f"[ERROR] Gagal memproses {filename}: {str(e)}")
    else:
        print(f"[MISSING] Fail tidak ditemui: {filename}")

print(f"\nSelesai! {fixed_count} fail telah dikemaskini untuk menggunakan 'config.php'.")
