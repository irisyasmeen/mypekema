import ftplib
import sys

FTP_HOST = "ftp.kliacustoms.net"
FTP_USER = "mypekema@kliacustoms.net"
FTP_PASS = "Iris6102009@#"

def delete_directory_recursive(ftp, path):
    try:
        ftp.cwd(path)
        items = ftp.nlst()
        for item in items:
            if item in [".", ".."]:
                continue
            try:
                ftp.delete(item)
                print(f"Deleted file: {item}")
            except ftplib.error_perm:
                delete_directory_recursive(ftp, item)
        ftp.cwd("..")
        ftp.rmd(path)
        print(f"Deleted directory: {path}")
    except Exception as e:
        print(f"Error deleting {path}: {e}")

def main():
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()
        print("Cleaning up nested folders...")
        # Delete public_html and its contents created by mistake
        delete_directory_recursive(ftp, "public_html")
        ftp.quit()
        print("Cleanup finished.")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    main()
