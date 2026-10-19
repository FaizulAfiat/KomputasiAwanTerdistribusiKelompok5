CREATE TABLE IF NOT EXISTS anggota (
  route_id VARCHAR(10) PRIMARY KEY,
  nim VARCHAR(15) NOT NULL,
  nama VARCHAR(100) NOT NULL
);

INSERT INTO anggota (route_id, nim, nama) VALUES
  ('m0', '103012400356', 'Faizul Afiat'),
  ('m1', '103012430017', 'Raynar Khalid Mahardika Sumarno'),
  ('m2', '103012400081', 'Aryo Dwipa Maulana')
ON CONFLICT (route_id) DO NOTHING;

CREATE TABLE IF NOT EXISTS guestbook (
  id SERIAL PRIMARY KEY,
  route_id VARCHAR(10) NOT NULL REFERENCES anggota(route_id),
  nama VARCHAR(100) NOT NULL,
  pesan TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);