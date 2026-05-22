from flask import Flask, request, jsonify
from flask_cors import CORS
import pdfplumber
import hashlib
import re
import os

app = Flask(__name__)
CORS(app) # Membenarkan cURL/AJAX dari domain luar seperti seal-uthm.site

# =========================================================================
# 📄 JALUR UTAMA MUKTAMAD: UNTUK UPLOAD PDF (100% IKUT FORMULA CREATE_MC_PROCESS / CREATE_TS_PROCESS)
# =========================================================================
def generate_pdf_hash(text):
    if not text:
        return None
        
    # Standardkan whitespace tetapi JANGAN paksa .upper() untuk mengekalkan format huruf 'May' / 'F'
    lines = [line.strip() for line in text.split('\n') if line.strip()]
    clean_text = " ".join(lines)
    clean_text_upper = clean_text.upper()
    
    # ─────────────────────────────────────────────────────────────────────────
    # 🛡️ MEKANISME KESELAMATAN: TAPIS KATA KUNCI DOKUMEN RASMI
    # ─────────────────────────────────────────────────────────────────────────
    if "MEDICAL CERTIFICATE" not in clean_text_upper and "TIME-SLIP" not in clean_text_upper:
        print("\n[SECURITY ALERT: Unknown document inserted! No official keywords found.]")
        return "INVALID_DOCUMENT_TYPE"
        
    # 1. Ekstrak NRIC (Cari 12 angka berturutan)
    nric_match = re.search(r'\d{12}', clean_text)
    nric = nric_match.group(0) if nric_match else ""

    # 2. Logik Pengasingan Ekstraksi Mengikut Jenis Dokumen
    if "MEDICAL CERTIFICATE" in clean_text_upper:
        # Ekstrak Tarikh Sijil Sakit (Contoh: 22 May 2026 atau 22 F Y)
        dates = re.findall(r'\d{2} [A-Za-z]{3} \d{4}', clean_text)
        start_date = dates[0] if len(dates) > 0 else ""
        end_date = dates[1] if len(dates) > 1 else ""
        
        # Ekstrak Diagnosis MC
        diagnosis = ""
        for i, line in enumerate(lines):
            if "DIAGNOSIS" in line.upper() or "PURPOSE" in line.upper():
                if i + 1 < len(lines):
                    diagnosis = lines[i+1].replace('"', '').replace('“', '').replace('”', '').strip().upper()
                break
        diagnosis = re.sub(r'[^A-Z0-9 ]', '', diagnosis).strip()

        # Ekstrak Doctor ID (Ambil angka dari SEAL_DID:X)
        doctor_id = "2"
        did_match = re.search(r'SEAL_DID:(\d+)', clean_text, re.IGNORECASE)
        if did_match:
            doctor_id = did_match.group(1).strip()

        # Ekstrak Masa GEN_TIME (Format HH:MM:SS)
        gen_time = ""
        time_match = re.search(r'GEN_TIME:(\d{2}:\d{2}:\d{2})', clean_text, re.IGNORECASE)
        if time_match:
            gen_time = time_match.group(1).strip()
        else:
            actual_times = re.findall(r'\d{2}:\d{2}:\d{2}', clean_text)
            gen_time = actual_times[-1].strip() if actual_times else ""

        # Formula Rantaian String Khas MC
        raw_string = str(nric).strip() + str(start_date).strip() + str(end_date).strip() + str(diagnosis).strip() + str(doctor_id).strip() + str(gen_time).strip()
        
    else:
        # ─── FORMAT KHAS UNTUK TIME SLIP ───
        # Ekstrak tarikh lawatan, masa mula, dan masa tamat (Contoh: 22 May 2026, 08:00 AM, 10:00 AM)
        # Sesuai dengan format rawData: trim(patientNRIC) + trim(visitDateStr) + trim(startTimeStr) + trim(endTimeStr) + trim(doctorID) + trim(currentTime)
        
        # Ekstrak Visit Date (Format: 22 May 2026 atau 22 F Y)
        date_match = re.search(r'\d{2} [A-Za-z]{3,9} \d{4}', clean_text)
        visit_date_str = date_match.group(0).strip() if date_match else ""
        
        # Ekstrak Waktu Mula & Tamat (Format: 08:00 AM)
        time_matches = re.findall(r'\d{2}:\d{2} [A-Z]{2}', clean_text)
        start_time_str = time_matches[0].strip() if len(time_matches) > 0 else ""
        end_time_str = time_matches[1].strip() if len(time_matches) > 1 else ""
        
        # Ekstrak Doctor ID
        doctor_id = "1"
        did_match = re.search(r'SEAL_DID:(\d+)', clean_text, re.IGNORECASE)
        if did_match:
            doctor_id = did_match.group(1).strip()
            
        # Ekstrak Current Time (GEN_TIME)
        gen_time = ""
        time_match = re.search(r'GEN_TIME:(\d{2}:\d{2}:\d{2})', clean_text, re.IGNORECASE)
        if time_match:
            gen_time = time_match.group(1).strip()

        # Formula Rantaian String Khas Time Slip
        raw_string = str(nric).strip() + str(visit_date_str).strip() + str(start_time_str).strip() + str(end_time_str).strip() + str(doctor_id).strip() + str(gen_time).strip()

    print(f"\n[EXECUTION: GEN ENGINE HASH]")
    print(f" -> Raw String Output: '{raw_string}'")
    
    return hashlib.sha256(raw_string.encode()).hexdigest()


@app.route('/process-pdf', methods=['POST'])
def process_pdf():
    if 'file' not in request.files:
        return jsonify({"status": "error", "message": "No file uploaded"}), 400
    file = request.files['file']
    try:
        with pdfplumber.open(file) as pdf:
            text = pdf.pages[0].extract_text()
            
        ocr_hash = generate_pdf_hash(text)
        return jsonify({"status": "success", "ocr_hash": ocr_hash, "extracted_text": text})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500


# FUTURE WORK: Papan Penyediaan untuk Modul Imbasan Imej Kamera Masa Hadapan
@app.route('/process-image', methods=['POST'])
def process_image():
    return jsonify({
        "status": "future_work", 
        "message": "Image and Camera OCR scanning module is designated for future development roadmap."
    }), 200


if __name__ == '__main__':
    # Pastikan pelayan membaca port dinamik dari Render Environment
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)