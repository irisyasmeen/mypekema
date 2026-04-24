import os
import shutil

# Konfigurasi
SOURCE_DIR = os.getcwd()
KEEP_DIR = os.path.join(SOURCE_DIR, 'keep')

# Pastikan folder 'keep' wujud
if not os.path.exists(KEEP_DIR):
    os.makedirs(KEEP_DIR)
    print(f"Folder dicipta: {KEEP_DIR}")

# Kriteria fail yang 'tidak dikehendaki' (sampah/lama/backup)
def is_unwanted(filename):
    # Jangan kacau fail ini
    if filename in ['index.php', 'config.php', 'penetration_test.py', 'fix_credentials.py', 'Laporan_Ujian_Penembusan.md', 'Laporan_Ujian_Penembusan.html']:
        return False
    
    # Kriteria Padanan
    patterns = [
        '-5Jan', '-6Jan', '-old', 'lama.php', '-keep', 
        '11google', '26Latest', 'AI-index', 'TERBAIK-AI', 'ai-tak-betul',
        'gbpekema-chatgpt', 'gbpekema-takjadi', 'google_auth11',
        'index-tiadaGraph', 'index500', 'indexb4', 'indexb5',
        'uji.php', 'debug_data.php', 'topmenu11'
    ]
    
    # Padanan Spesifik (Exact Match or Startswith)
    if filename.startswith('index-') and filename != 'index.php':
        return True
    
    for p in patterns:
        if p.lower() in filename.lower():
            return True
            
    return False

# Proses Pemindahan
moved_count = 0
for filename in os.listdir(SOURCE_DIR):
    file_path = os.path.join(SOURCE_DIR, filename)
    
    # Hanya proses fail (bukan folder)
    if os.path.isfile(file_path):
        if is_unwanted(filename):
            try:
                dest_path = os.path.join(KEEP_DIR, filename)
                shutil.move(file_path, dest_path)
                print(f"Dipindahkan: {filename}")
                moved_count += 1
            except Exception as e:
                print(f"Ralat memindahkan {filename}: {e}")

print(f"\nSelesai! {moved_count} fail telah dipindahkan ke folder 'keep'.")
