// Icon Generator Script
// Install sharp first: npm install sharp

const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const sizes = [72, 96, 128, 144, 152, 192, 384, 512];
const sourceIcon = 'source-icon.png'; // Place your source icon here (512x512 or larger)

async function generateIcons() {
  if (!fs.existsSync(sourceIcon)) {
    console.error(`Error: ${sourceIcon} not found!`);
    console.log('Please place a source-icon.png file (512x512 or larger) in this directory.');
    return;
  }

  console.log('Generating PWA icons...');

  for (const size of sizes) {
    const outputFile = `icon-${size}x${size}.png`;
    
    try {
      await sharp(sourceIcon)
        .resize(size, size, {
          fit: 'contain',
          background: { r: 255, g: 255, b: 255, alpha: 0 }
        })
        .png()
        .toFile(outputFile);
      
      console.log(`✓ Generated ${outputFile}`);
    } catch (error) {
      console.error(`✗ Failed to generate ${outputFile}:`, error.message);
    }
  }

  console.log('\nAll icons generated successfully!');
}

generateIcons().catch(console.error);
