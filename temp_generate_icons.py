import os
import struct
import zlib


def chunk(tag: bytes, data: bytes) -> bytes:
    return struct.pack('!I', len(data)) + tag + data + struct.pack('!I', zlib.crc32(tag + data) & 0xFFFFFFFF)


def create_solid_png(path: str, size: int, rgba: tuple[int, int, int, int]) -> None:
    width = height = size
    os.makedirs(os.path.dirname(path), exist_ok=True)

    # Each row: filter byte 0 + pixels
    row = bytes([0]) + bytes(rgba) * width
    raw = row * height

    png = bytearray()
    png.extend(b"\x89PNG\r\n\x1a\n")
    png.extend(chunk(b'IHDR', struct.pack('!IIBBBBB', width, height, 8, 6, 0, 0, 0)))
    png.extend(chunk(b'IDAT', zlib.compress(raw)))
    png.extend(chunk(b'IEND', b''))

    with open(path, 'wb') as fh:
        fh.write(png)


def main() -> None:
    base_dir = os.path.join('assets', 'images', 'icons')
    full_base = os.path.join(os.path.dirname(__file__), base_dir)
    brand_color = (13, 110, 253, 255)  # Bootstrap primary

    create_solid_png(os.path.join(full_base, 'icon-192.png'), 192, brand_color)
    create_solid_png(os.path.join(full_base, 'icon-512.png'), 512, brand_color)


if __name__ == '__main__':
    main()
