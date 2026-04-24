import os
import ftplib
import sys

FTP_HOST = "ftp.kliacustoms.net"
FTP_USER = "mypekema@kliacustoms.net"
FTP_PASS = "Iris6102009@#"
FTP_DIR  = "/"

LOCAL_DIR = r"c:\xampp\htdocs\mypekema"

# Ignore list
IGNORE = [".git", "uploads", "node_modules", ".vscode", "keep", "check_users_schema.php", "check_allowed_users_schema.php", "list_tables.php", "check_gb_schema.php"]
IGNORE_EXT = [".zip", ".rar", ".7z", ".tmp", ".log"]

def upload_directory(ftp, local_path, remote_path):
    print(f"Checking remote directory: {remote_path}")
    try:
        ftp.cwd(remote_path)
    except ftplib.error_perm:
        print(f"Creating remote directory: {remote_path}")
        parts = remote_path.split("/")
        curr = ""
        for part in parts:
            if not part: continue
            curr += "/" + part
            try:
                ftp.mkd(curr)
                print(f"Created: {curr}")
            except:
                pass
        ftp.cwd(remote_path)

    items = os.listdir(local_path)
    print(f"Found {len(items)} items in {local_path}")

    for item in items:
        if item in IGNORE:
            continue
        
        local_item_path = os.path.join(local_path, item)
        remote_item_path = remote_path + "/" + item

        if os.path.isfile(local_item_path):
            ext = os.path.splitext(item)[1].lower()
            if ext in IGNORE_EXT:
                continue
            
            print(f"Uploading: {item} ...")
            try:
                with open(local_item_path, "rb") as f:
                    ftp.storbinary(f"STOR {item}", f)
                print(f"Success: {item}")
            except Exception as e:
                print(f"Failed to upload {item}: {e}")
        
        elif os.path.isdir(local_item_path):
            print(f"Entering directory: {item} ...")
            upload_directory(ftp, local_item_path, remote_item_path)
            ftp.cwd("..")

def main():
    print(f"Connecting to {FTP_HOST} (Secure TLS) ...")
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST)
        ftp.set_debuglevel(1) # Enable debug output
        print("Authenticating...")
        ftp.login(FTP_USER, FTP_PASS)
        print("Logged in. Setting up secure data channel...")
        ftp.prot_p()
        print("Secure channel ready. Setting passive mode...")
        ftp.set_pasv(True)
        
        upload_directory(ftp, LOCAL_DIR, FTP_DIR)
        
        ftp.quit()
        print("\nDeployment completed successfully!")
    except Exception as e:
        print(f"\nError details: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
