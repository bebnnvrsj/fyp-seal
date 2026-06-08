const { ethers } = require("ethers");
const express = require("express");
const cors = require("cors");

const app = express();
app.use(cors());

// --- KONFIGURASI NETWORK & WALLET ---
const provider = new ethers.JsonRpcProvider("https://sepolia.infura.io/v3/aa4d57b2d736465ba4c151d14575fce7");
const wallet = new ethers.Wallet("0x983c9feb5ed9b6ac8aaa2fa35c635469b0a867c986a3329eafb9d3945c98872a", provider);
const contractAddress = "0xCacAF0F96104C9dB256d136533df168B887c2125";

// ABI bytes32 selaras dengan seal.sol yang baru
const abi = [
    "function recordHash(bytes32 _docHash) public",
    "function verifyHash(bytes32 _docHash) public view returns (bool isValid, uint256 timestamp)"
];

const contract = new ethers.Contract(contractAddress, abi, wallet);

/**
 * FUNGSI 1: Mendaftar Hash
 * Digunakan oleh PHP melalui shell_exec
 */
async function sendHash(rawHash) {
    try {
        if (!rawHash) return;
        
        // Pastikan hash ada prefix 0x supaya ethers boleh baca sebagai bytes32
        const formattedHash = rawHash.startsWith("0x") ? rawHash : "0x" + rawHash;
        
        // Hantar transaksi ke Sepolia
        const tx = await contract.recordHash(formattedHash);
        
        // Tunggu transaksi disahkan
        await tx.wait();
        
        // Print TxHash untuk dibaca oleh PHP
        process.stdout.write(tx.hash); 
    } catch (error) {
        process.stderr.write("Blockchain Registration Error: " + error.message + "\n");
        process.exit(1);
    }
}

/**
 * FUNGSI 2: Verifikasi Hash (API Endpoint)
 */
app.get("/verify-on-blockchain/:hash", async (req, res) => {
    try {
        let inputHash = req.params.hash;

        // Tambah 0x jika tiada
        if (!inputHash.startsWith("0x")) {
            inputHash = "0x" + inputHash;
        }

        const result = await contract.verifyHash(inputHash);
        
        res.json({
            isValid: result[0],
            timestamp: result[1].toString()
        });
    } catch (error) {
        res.status(500).json({ isValid: false, error: error.message });
    }
});

// --- JALANKAN SERVER ---
const PORT = 3000;
app.listen(PORT, () => {
    process.stderr.write(`Relayer Server running on http://localhost:${PORT}\n`);
});

// Logik untuk shell_exec PHP
if (process.argv[2]) {
    sendHash(process.argv[2]);
}