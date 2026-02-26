/**
 * Minimal tar unpacker for loading Laravel app files into Emscripten's MEMFS.
 * Supports POSIX ustar format (512-byte headers).
 */

interface EmscriptenFS {
  mkdir(path: string): void;
  writeFile(path: string, data: Uint8Array): void;
  analyzePath(path: string): { exists: boolean };
}

function mkdirp(FS: EmscriptenFS, path: string): void {
  const parts = path.split("/").filter(Boolean);
  let current = "";
  for (const part of parts) {
    current += "/" + part;
    if (!FS.analyzePath(current).exists) {
      FS.mkdir(current);
    }
  }
}

export function untar(FS: EmscriptenFS, buffer: ArrayBuffer, prefix = ""): void {
  const data = new Uint8Array(buffer);
  let offset = 0;

  while (offset + 512 <= data.length) {
    // Check for end-of-archive (two consecutive zero blocks)
    const header = data.subarray(offset, offset + 512);
    if (header.every((b) => b === 0)) {
      break;
    }

    // Parse header fields
    const name = readString(header, 0, 100);
    const size = readOctal(header, 124, 12);
    const typeFlag = header[156];
    const ustarPrefix = readString(header, 345, 155);

    const fullName = ustarPrefix ? ustarPrefix + "/" + name : name;
    const destPath = prefix ? prefix + "/" + fullName : "/" + fullName;

    offset += 512;

    // Type: '5' = directory, '0' or NUL = regular file
    if (typeFlag === 53) {
      // Directory
      mkdirp(FS, destPath.replace(/\/$/, ""));
    } else if (typeFlag === 48 || typeFlag === 0) {
      // Regular file
      const dir = destPath.substring(0, destPath.lastIndexOf("/"));
      if (dir) {
        mkdirp(FS, dir);
      }
      const fileData = data.slice(offset, offset + size);
      FS.writeFile(destPath, fileData);
    }

    // Advance past file data (rounded up to 512-byte boundary)
    offset += Math.ceil(size / 512) * 512;
  }
}

function readString(buf: Uint8Array, offset: number, length: number): string {
  let end = offset;
  const limit = offset + length;
  while (end < limit && buf[end] !== 0) {
    end++;
  }
  return new TextDecoder().decode(buf.subarray(offset, end));
}

function readOctal(buf: Uint8Array, offset: number, length: number): number {
  const str = readString(buf, offset, length).trim();
  return str ? parseInt(str, 8) : 0;
}
