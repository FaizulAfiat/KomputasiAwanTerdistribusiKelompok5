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

CREATE TABLE IF NOT EXISTS pertanyaan (
  id SERIAL PRIMARY KEY,
  route_id VARCHAR(10) NOT NULL REFERENCES anggota(route_id),
  nama VARCHAR(100) NOT NULL,
  isi TEXT NOT NULL,
  upvote INT NOT NULL DEFAULT 0 CHECK (upvote >= 0),
  disembunyikan BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS jawaban (
  id SERIAL PRIMARY KEY,
  pertanyaan_id INT NOT NULL REFERENCES pertanyaan(id) ON DELETE CASCADE,
  route_id VARCHAR(10) NOT NULL REFERENCES anggota(route_id),
  isi TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_pertanyaan_urut ON pertanyaan (upvote DESC, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_jawaban_pertanyaan ON jawaban (pertanyaan_id);

GRANT USAGE ON SCHEMA public TO app_local;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_local;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_local;