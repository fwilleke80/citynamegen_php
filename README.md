# City Name Generator (PHP, Web)

This is a **PHP port** of the original Python *cityname* script.  
It generates random German-style (and sometimes funny) city names from syllable sets stored in a JSON file.  

The script is **web-only** — no CLI. Drop the two files into the same folder on your web server:

- `index.php` — the generator script (open this in the browser).
- `citynamegen_data.json` — data file containing prefixes, suffixes, and name parts.

---

## Features

- **Base names** built from two parts (`parts[0]` + `parts[1]`).
- **Optional prefix** (e.g. *Alt*, *Neu*, *Bad*) with configurable probability.
- **Optional suffix** (e.g. *am Rhein*, *im Harz*) with configurable probability.
- **Optional hyphenated double names** (e.g. *Dortmund-Bielefeld*) with configurable probability.
- **Statistics view** shows total available syllables and combinatorics.

All thresholds and probabilities are read from the JSON `settings` block.

---

## Usage

1. Copy `index.php` and `citynamegen_data.json` into the same directory on your PHP-enabled server.
2. Open the directory in your browser (e.g. `https://yourdomain.tld/citygen/`).

You’ll see a form with the following options:

- **Count**  
  Number of city names to generate
- **Show statistics**  
  Displays a breakdown of syllable counts and possible name combinations

Click **Ausführen** to generate results.

---

## Example

Generate 5 random city names:

1. Bad Klötenheim
2. Ober Ritzenberg
3. Puffendorf am Rhein
4. Neu Saftbrück
5. Trottelstadt

Show statistics:

### Parts:
First parts : 120  
Second parts : 80  
Base names (P0×P1) : 9,600  

### Affixes:
Prefixes : 8  
Suffixes : 24  
### Combinations (no probabilities applied):

Base only : 9,600  
With prefixes : 86,400  
With suffixes : 240,000  
With both : 2,160,000  
Hyphenated double : 92,160,000  
Approx total incl dbl : 92,162,160  


*(Numbers depend on the actual contents of your `citynamegen_data.json`.)*

---

## Requirements

- PHP 8.0 or newer
- A web server with PHP enabled
- `mbstring` extension (for proper case conversion)

---

## License

This project is released into the public domain under [The Unlicense](https://unlicense.org/).

You are free to copy, modify, distribute, and use the code and data for any purpose, commercial or non-commercial, without restriction.  

### Disclaimer
This software is provided "AS IS", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose, and noninfringement. In no event shall the authors be liable for any claim, damages, or other liability, whether in an action of contract, tort, or otherwise, arising from, out of, or in connection with the software or the use or other dealings in the software.
