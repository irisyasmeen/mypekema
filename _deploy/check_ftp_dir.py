import ftplib
import sys

FTP_HOST = "ftp.kliacustoms.net"
FTP_USER = "mypekema@kliacustoms.net"
FTP_PASS = "Iris6102009@#"

def main():
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()
        print(f"Current Directory after login: {ftp.pwd()}")
        print("Directory listing:")
        ftp.dir()
        ftp.quit()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    main()
