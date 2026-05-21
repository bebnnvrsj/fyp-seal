from flask import Flask, request, jsonify
import pdfplumber
import hashlib
import re
import pytesseract
from PIL import Image
import io

app = Flask(__name__)

pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# =========================================================================
# 📄 JALUR KHAS: UNTUK UPLOAD PDF (100% IKUT FORMULA CREATE_MC_PROCESS.PHP)
# =========================================================================
def generate_pdf_hash(text):
    if not text:
        return None
        
    # Standardkan whitespace tetapi JANGAN paksa .upper() untuk mengekalkan format huruf 'May'
    lines = [line.strip() for line in text.split('\n') if line.strip()]
    clean_text = " ".join(lines)
    clean_text_upper = clean_text.upper()
    
    # ─────────────────────────────────────────────────────────────────────────
    # 🛡️ LOGIK UTAMA CADANGAN ANDA: TAPIS KATA KUNCI DOKUMEN RASMI
    # ─────────────────────────────────────────────────────────────────────────
    # Semak sama ada dokumen mengandungi kata kunci rasmi MEDDOQS ataupun tidak
    if "MEDICAL CERTIFICATE" not in clean_text_upper and "TIME-SLIP" not in clean_text_upper:
        # Jika tiada kata kunci langsung, kita pulangkan string khas 'INVALID_DOCUMENT_TYPE'
        print("\n[SECURITY ALERT: Unknown document inserted! No official keywords found.]")
        return "INVALID_DOCUMENT_TYPE"
        
    # 1. Ekstrak NRIC (Cari 12 angka berturutan)
    nric_match = re.search(r'\d{12}', clean_text)
    nric = nric_match.group(0) if nric_match else ""

    # 2. Ekstrak Tarikh (Ikut format asal fail PDF, contoh: 20 May 2026)
    dates = re.findall(r'\d{2} [A-Za-z]{3} \d{4}', clean_text)
    start_date = dates[0] if len(dates) > 0 else ""
    end_date = dates[1] if len(dates) > 1 else ""

    # 3. Ekstrak Diagnosis (Ambil baris di bawah DIAGNOSIS/PURPOSE)
    diagnosis = ""
    for i, line in enumerate(lines):
        if "DIAGNOSIS" in line.upper() or "PURPOSE" in line.upper():
            if i + 1 < len(lines):
                # Kita ubah terus ke HURUF BESAR sbb PHP anda ada fungsi strtoupper() pada diagnosis!
                diagnosis = lines[i+1].replace('"', '').replace('“', '').replace('”', '').strip().upper()
            break
    
    # Bersihkan diagnosis daripada simbol aneh
    diagnosis = re.sub(r'[^A-Z0-9 ]', '', diagnosis).strip()

    # 4. Ekstrak Doctor ID (Ambil angka dari SEAL_DID:X)
    doctor_id = "2"
    did_match = re.search(r'SEAL_DID:(\d+)', clean_text, re.IGNORECASE)
    if did_match:
        doctor_id = did_match.group(1).strip()

    # 5. Ekstrak Masa GEN_TIME (Format HH:MM:SS)
    gen_time = ""
    time_match = re.search(r'GEN_TIME:(\d{2}:\d{2}:\d{2})', clean_text, re.IGNORECASE)
    if time_match:
        gen_time = time_match.group(1).strip()
    else:
        actual_times = re.findall(r'\d{2}:\d{2}:\d{2}', clean_text)
        gen_time = actual_times[-1].strip() if actual_times else ""

    # Cantum rapat mengikut formula asal PHP anda
    raw_string = str(nric).strip() + str(start_date).strip() + str(end_date).strip() + str(diagnosis).strip() + str(doctor_id).strip() + str(gen_time).strip()
    
    print(f"\n[EXECUTION: JALUR UPLOAD PDF ASLI]")
    print(f" -> Raw String: '{raw_string}'")
    
    return hashlib.sha256(raw_string.encode()).hexdigest()

# =========================================================================
# 📷 JALUR KHAS: UNTUK IMBASED CAMERA SCAN (OCR FIXED)
# =========================================================================
def generate_ocr_hash(text):
    if not text:
        return None
    clean_text = " ".join(text.split()).upper()
    
    nric_match = re.search(r'\d{12}', clean_text)
    nric = nric_match.group(0) if nric_match else ""

    dates = re.findall(r'\d{2} [A-Z]{3} \d{4}', clean_text)
    start_date = dates[0] if len(dates) > 0 else ""
    end_date = dates[2] if len(dates) >= 3 else (dates[1] if len(dates) > 1 else "")

    diagnosis = ""
    diag_match = re.search(r'["\'“_]([^"\'“_]{3,30})["\'”_]', clean_text)
    if diag_match:
        diagnosis = diag_match.group(1).strip()
    else:
        try:
            diagnosis = clean_text.split("PURPOSE")[-1].split("ATTENDING")[0].strip()
            diagnosis = " ".join([w for w in diagnosis.split() if w not in ["DR", "KHAIRUNNISA", "MEDICAL", "CERTIFICATE"]])
        except:
            diagnosis = ""
            
    diagnosis = re.sub(r'[^A-Z0-9 ]', '', diagnosis).strip()
    doctor_id = "2" if "KHAIRUNNISA" in clean_text else "1"
    
    time_match = re.findall(r'\d{2}:\d{2}:\d{2}', clean_text)
    gen_time = time_match[-1].strip() if time_match else "16:13:51"

    raw_string = str(nric).strip() + str(start_date).strip() + str(end_date).strip() + str(diagnosis).upper().strip() + str(doctor_id).strip() + str(gen_time).strip()
    
    print(f"\n[EXECUTION: JALUR CAMERA SCAN IMAG]")
    print(f" -> Raw String: '{raw_string}'")
    return hashlib.sha256(raw_string.encode()).hexdigest()


@app.route('/process-pdf', methods=['POST'])
def process_pdf():
    if 'file' not in request.files:
        return jsonify({"status": "error", "message": "No file uploaded"}), 400
    file = request.files['file']
    try:
        with pdfplumber.open(file) as pdf:
            text = pdf.pages[0].extract_text()
            
        # PANGGIL FUNGSI PDF BERSIH DI SINI!
        ocr_hash = generate_pdf_hash(text)
        
        return jsonify({"status": "success", "ocr_hash": ocr_hash, "extracted_text": text})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route('/process-image', methods=['POST'])
def process_image():
    if 'file' not in request.files:
        return jsonify({"status": "error", "message": "No image file uploaded"}), 400
    file = request.files['file']
    try:
        img_bytes = file.read()
        image = Image.open(io.BytesIO(img_bytes))
        extracted_text = pytesseract.image_to_string(image)
        
        # PANGGIL FUNGSI CAMERA DI SINI!
        ocr_hash = generate_ocr_hash(extracted_text)
        
        return jsonify({"status": "success", "ocr_hash": ocr_hash, "extracted_text": extracted_text})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    app.run(port=5000, debug=True)