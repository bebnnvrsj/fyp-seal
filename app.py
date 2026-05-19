from flask import Flask, request, jsonify
import pdfplumber
import hashlib
import re

app = Flask(__name__)

def generate_ocr_hash(text):
    clean_text = " ".join(text.split())
    
    # 1. Ekstrak NRIC
    nric_match = re.search(r'\d{12}', clean_text)
    nric = nric_match.group(0) if nric_match else ""

    # 2. Ekstrak DoctorID yang tersembunyi tadi
    did_match = re.search(r'SEAL_DID:(\d+)', clean_text)
    doctor_id = did_match.group(1) if did_match else "1" # Default ke 1 jika gagal

    if "MEDICAL CERTIFICATE" in clean_text.upper():
        # Formula MC: NRIC + StartDate + EndDate + Diagnosis + DoctorID[cite: 11]
        dates = re.findall(r'\d{2} [A-Z][a-z]{2} \d{4}', clean_text)
        start_date = dates[0] if len(dates) > 0 else ""
        end_date = dates[1] if len(dates) > 1 else ""
        
        diag_match = re.search(r'\"(.*?)\"', clean_text)
        diagnosis = diag_match.group(1).upper().strip() if diag_match else ""
        
        raw_string = nric + start_date + end_date + diagnosis + doctor_id

    elif "TIME-SLIP" in clean_text.upper():
        # Formula TS: NRIC + VisitDate + TimeIn + TimeOut + DoctorID[cite: 12]
        date_match = re.search(r'\d{2} [A-Z][a-z]{2} \d{4}', clean_text)
        visit_date = date_match.group(0) if date_match else ""
        
        times = re.findall(r'\d{2}:\d{2} [AP]M', clean_text)
        time_in = times[0] if len(times) > 0 else ""
        time_out = times[1] if len(times) > 1 else ""
        
        diag_match = re.search(r'\"(.*?)\"', clean_text)
        diagnosis = diag_match.group(1).upper().strip() if diag_match else ""
        
        raw_string = nric + visit_date + time_in + time_out + doctor_id
    
    return hashlib.sha256(raw_string.encode()).hexdigest()

@app.route('/process-pdf', methods=['POST'])
def process_pdf():
    if 'file' not in request.files:
        return jsonify({"status": "error", "message": "No file uploaded"}), 400
    
    file = request.files['file']
    try:
        with pdfplumber.open(file) as pdf:
            text = pdf.pages[0].extract_text()
            
        ocr_hash = generate_ocr_hash(text)
        
        if ocr_hash is None:
            return jsonify({"status": "error", "message": "Unknown document type"}), 400
            
        return jsonify({
            "status": "success",
            "ocr_hash": ocr_hash,
            "extracted_text": text # Untuk debugging
        })
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    app.run(port=5000, debug=True)