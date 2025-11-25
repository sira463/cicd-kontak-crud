<?php

use PHPUnit\Framework\TestCase;

// yang akan dipanggil oleh PHPUnit.
class MockFunctions {
    public static function file_get_contents($path) {
        if ($path === 'php://input' && isset($GLOBALS['mock_input'])) {
            return $GLOBALS['mock_input'];
        }
        return \file_get_contents($path);
    }
}


if (!function_exists('file_get_contents')) {
    function file_get_contents($path) {
        return MockFunctions::file_get_contents($path);
    }
}

class ApiTest extends TestCase
{
    private $apiPath = 'api.php';
    private $conn;
    private $testId = null;

    protected function setUp(): void
    {
        // Hubungkan ke database menggunakan konstanta dari phpunit.xml
        $this->conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($this->conn->connect_error) {
            $this->markTestSkipped('Koneksi database gagal: ' . $this->conn->connect_error);
        }
        
        // Bersihkan data lama dari tes sebelumnya
        $this->conn->query("DELETE FROM kontak WHERE nama LIKE 'TEST-%'");
    }

    protected function tearDown(): void
    {
        // Bersihkan data yang dibuat selama pengujian
        $this->conn->query("DELETE FROM kontak WHERE nama LIKE 'TEST-%'");
        $this->conn->close();
    }
    
    // --- Pengujian CRUD 
    
    /**
   * Membuat Kontak Sukses (POST)
     */
    public function testCreateContactSuccess()
    {
        $data = [
            'nama' => 'TEST-Create',
            'telepon' => '08111222333',
            'email' => 'test.create@gmail.com'
        ];

        // Simulasikan Request POST
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $GLOBALS['mock_input'] = json_encode($data); 
        
        ob_start();
        include $this->apiPath;
        $response = ob_get_clean();

        $responseData = json_decode($response, true);

        $this->assertEquals('success', $responseData['status'], "Gagal membuat kontak valid.");
        $this->assertStringContainsString('Kontak berhasil disimpan', $responseData['message']);
        
        // Ambil ID untuk pengujian selanjutnya
        $result = $this->conn->query("SELECT id FROM kontak WHERE nama='TEST-Create'");
        $this->testId = $result->fetch_assoc()['id'];
        $this->assertNotNull($this->testId);
    }

    /**
     *  Cari Kontak (GET)
     */
    public function testGetContactWithSearch()
    {
        // Tambahkan data dummy untuk pencarian
        $this->conn->query("INSERT INTO kontak (nama, telepon) VALUES ('TEST-Andi', '081')");
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['search'] = 'TEST-Andi'; 

        ob_start();
        include $this->apiPath;
        $response = ob_get_clean();

        $responseData = json_decode($response, true);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertGreaterThan(0, count($responseData['data']), "Pencarian 'TEST-Andi' gagal menemukan kontak.");
    }
    
    /**
     * TC-10: Edit Kontak Sukses (PUT)
     */
    public function testUpdateContactSuccess()
    {
        // Pastikan ada data untuk diupdate
        $this->conn->query("INSERT INTO kontak (nama, telepon) VALUES ('TEST-Update-Old', '08555')");
        $idToUpdate = $this->conn->insert_id;
        
        $data = [
            'id' => $idToUpdate,
            'nama' => 'TEST-Update-New',
            'telepon' => '08777999',
            'email' => 'update.new@gmail.com'
        ];

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $GLOBALS['mock_input'] = json_encode($data); 
        
        ob_start();
        include $this->apiPath;
        $response = ob_get_clean();
        
        $responseData = json_decode($response, true);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertStringContainsString('Kontak berhasil diperbarui', $responseData['message']);
        
        // Verifikasi di DB
        $result = $this->conn->query("SELECT nama FROM kontak WHERE id='$idToUpdate'");
        $this->assertEquals('TEST-Update-New', $result->fetch_assoc()['nama']);
    }

    /**
     * Hapus Kontak Sukses (DELETE)
     */
    public function testDeleteContactSuccess()
    {
        // Pastikan ada data untuk dihapus
        $this->conn->query("INSERT INTO kontak (nama, telepon) VALUES ('TEST-Delete', '08444')");
        $idToDelete = $this->conn->insert_id;

        $data = ['id' => $idToDelete];

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $GLOBALS['mock_input'] = json_encode($data); 
        
        ob_start();
        include $this->apiPath;
        $response = ob_get_clean();

        $responseData = json_decode($response, true);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertStringContainsString('Kontak berhasil dihapus', $responseData['message']);
    }

    // --- Pengujian Validasi  ---
    
    /**
     * Validasi Gagal (No HP berisi huruf)
     */
    public function testCreateContactFailureInvalidPhone()
    {
        $data = [
            'nama' => 'TEST-Gagal-HP',
            'telepon' => '08ABCD123', // Invalid
            'email' => 'gagal@gmail.com'
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $GLOBALS['mock_input'] = json_encode($data);
        
        ob_start();
        include $this->apiPath;
        $response = ob_get_clean();

        $responseData = json_decode($response, true);
        
        $this->assertEquals('error', $responseData['status']);
        $this->assertStringContainsString('Nomor Telepon hanya boleh angka', $responseData['message'], "Gagal menangkap TC-06.");
    }

    /**
     * Validasi Gagal (Nama kosong)
     */
    public function testCreateContactFailureEmptyName()
    {
        $data = [
            'nama' => '', // Invalid
            'telepon' => '08123456',
            'email' => 'gagal@gmail.com'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $GLOBALS['mock_input'] = json_encode($data);
        ob_start();
        include $this->apiPath;
        $response = ob_get_clean();
        $responseData = json_decode($response, true);
        $this->assertStringContainsString('Nama tidak boleh kosong', $responseData['message'], "Gagal menangkap TC-04.");
    }
}