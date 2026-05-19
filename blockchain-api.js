const express = require('express');
const { ethers } = require('ethers');
const cors = require('cors');

const app = express();
app.use(express.json());
app.use(cors());

const INFURA_URL = "https://sepolia.infura.io/v3/aa4d57b2d736465ba4c151d14575fce7";
const CONTRACT_ADDRESS = "0x3C757C585dA130EFF5F97582B72f2454c830C406";

const ABI = [
    "function verifyHash(string memory _docHash) public view returns (uint256)"
];

const provider = new ethers.JsonRpcProvider(INFURA_URL);

// Baris 18 sekarang sudah boleh membaca pembolehubah ABI di atas
const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, provider);

app.get('/verify-on-blockchain/:hash', async (req, res) => {
    try {
        const docHash = req.params.hash;
        
        // Panggil verifyHash seperti dalam seal.sol baris 15
        const timestamp = await contract.verifyHash(docHash.toLowerCase());
        
        // Jika timestamp bukan 0, maksudnya dokumen sah!
        const isValid = Number(timestamp) > 0;
        
        res.json({ isValid: isValid });
    } catch (error) {
        res.status(500).json({ isValid: false, error: error.message });
    }
});    

app.listen(3000, () => console.log("Blockchain API Bridge running on port 3000"));