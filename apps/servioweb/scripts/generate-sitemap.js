/**
 * Build-time sitemap generator that mirrors the Pages Router implementation.
 *
 * Usage:
 *   node scripts/generate-sitemap.js
 *
 * Output:
 *   public/sitemap.xml
 */

import { writeFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import dotenv from 'dotenv';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
dotenv.config({ path: join(__dirname, '..', '.env.local') });
dotenv.config({ path: join(__dirname, '..', '.env') });

const BASE_URL = process.env.NEXT_PUBLIC_WEB_URL;

async function run() {
  try {
    const { generateSitemapXml } = await import('../src/utils/sitemapBuilder.js');

    console.log('\n🚀 Starting sitemap generation...\n');

    const sitemapXml = await generateSitemapXml({ baseUrl: BASE_URL });

    const outputPath = join(__dirname, '..', 'public', 'sitemap.xml');
    writeFileSync(outputPath, sitemapXml, 'utf-8');

    console.log('✅ Sitemap generated successfully!');
    console.log(`📁 Location: ${outputPath}`);
    console.log(`🔗 Base URL: ${BASE_URL}\n`);
  } catch (error) {
    console.error('\n❌ Error generating sitemap:', error);
    process.exit(1);
  }
}

run();

