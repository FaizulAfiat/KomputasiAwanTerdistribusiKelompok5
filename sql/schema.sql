CREATE TABLE IF NOT EXISTS anggota (
  route_id VARCHAR(10) PRIMARY KEY,
  nim VARCHAR(15) NOT NULL,
  nama VARCHAR(100) NOT NULL
);

INSERT INTO anggota (route_id, nim, nama) VALUES
  ('m0', '103012400356', 'Faizul Afiat'),
  ('m1', '103012430017', 'Raynar Khalid Mahardika Sumarno'),
  ('m2', '103012400081', 'Aryo Dwipa Maulana'),
  ('m3', '103012430044', 'Zakiyah Nabilah Az Zahra'),
  ('m4', '103012430064', 'Annisa Roslina Anwar')
ON CONFLICT (route_id) DO NOTHING;