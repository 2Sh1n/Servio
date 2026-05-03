/**
 * Sitemap Setup Script
 * 
 * This script handles sitemap generation based on SEO configuration:
 * - If SEO is enabled: Ensures sitemap.xml.js exists in src/pages for server-side rendering
 * - If SEO is disabled: Generates static sitemap.xml in public folder
 * 
 * Usage:
 *   node scripts/setup-sitemap.js
 */

import { existsSync, writeFileSync, readFileSync, unlinkSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import dotenv from 'dotenv';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Load environment variables
dotenv.config({ path: join(__dirname, '..', '.env.local') });
dotenv.config({ path: join(__dirname, '..', '.env') });

const SEO_ENABLED = process.env.NEXT_PUBLIC_ENABLE_SEO === "true";
const SITEMAP_PAGE_PATH = join(__dirname, '..', 'src', 'pages', 'sitemap.xml.js');
const PUBLIC_SITEMAP_PATH = join(__dirname, '..', 'public', 'sitemap.xml');

// Server-side sitemap template (for when SEO is enabled)
const SERVER_SIDE_SITEMAP_TEMPLATE = `/**
 * Dynamic XML Sitemap Generator for Next.js 14 (Pages Router)
 *
 * This file generates a valid XML sitemap at /sitemap.xml
 * It follows Google's sitemap protocol and includes:
 * - All static pages
 * - Dynamic routes (discovered from pages directory)
 * - Multi-language support with hreflang tags
 * - SEO metadata (priority, changefreq, lastmod)
 *
 * Compatible with Next.js 14.2.33 (Pages Router)
 * 
 * Conditional rendering:
 * - If NEXT_PUBLIC_ENABLE_SEO="true" → uses getServerSideProps (server-side rendering)
 * - If NEXT_PUBLIC_ENABLE_SEO="false" or not set → sitemap is generated statically
 * 
 * We intentionally avoid importing sitemapBuilder at the top level.
 * Instead we dynamically import it inside getServerSideProps so Node-only
 * dependencies like globby never end up in the client bundle during build.
 */

/**
 * Determine the base URL for the sitemap
 * Uses environment variables and request headers
 */
const resolveBaseUrl = (req) => {
  // In development, build URL dynamically from request headers
  if (process.env.NODE_ENV === 'development' && req) {
    const protocol = req.headers['x-forwarded-proto'] || 'http';
    const host = req.headers.host || 'localhost:3000';
    return \`\${protocol}://\${host}\`;
  }

  // In production, use the configured public web URL
  return process.env.NEXT_PUBLIC_WEB_URL;
};

const FALLBACK_ROUTES = [
  '/',
  '/about-us',
  '/contact-us',
  '/faqs',
  '/blogs',
  '/services',
  '/providers',
  '/become-provider',
  '/privacy-policy',
  '/terms-and-conditions',
];

const buildFallbackSitemap = (baseUrl) => {
  const safeBaseUrl = baseUrl || process.env.NEXT_PUBLIC_WEB_URL;

  const urlEntries = FALLBACK_ROUTES.map((route) => {
    const safeRoute = route === '/' ? '' : route;
    return [
      '  <url>',
      \`    <loc>\${safeBaseUrl}\${safeRoute}</loc>\`,
      \`    <lastmod>\${new Date().toISOString()}</lastmod>\`,
      '    <changefreq>weekly</changefreq>',
      '    <priority>0.7</priority>',
      '  </url>',
    ].join('\\n');
  });

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    '  <!-- Fallback sitemap generated due to runtime error -->',
    ...urlEntries,
    '</urlset>',
  ].join('\\n');
};

// Conditionally export getServerSideProps (like other pages do)
// When SEO is enabled: export the handler function (server-side API calls for sitemap)
// When SEO is disabled: export null (Next.js will ignore it)
let serverSidePropsFunction = null;

if (process.env.NEXT_PUBLIC_ENABLE_SEO === "true") {
  /**
   * Server-side function to generate and serve the XML sitemap.
   * This runs on every request to /sitemap.xml when SEO is enabled.
   * 
   * We end the response manually here — preventing React from rendering anything.
   */
  serverSidePropsFunction = async ({ req, res }) => {
    const baseUrl = resolveBaseUrl(req);

    try {
      // Dynamic import keeps Node-only dependencies out of the client bundle
      const { generateSitemapXml } = await import('@/utils/sitemapBuilder');
      const sitemapXml = await generateSitemapXml({ baseUrl });

      // ✅ Always set headers before writing data
      res.statusCode = 200;
      res.setHeader('Content-Type', 'application/xml; charset=utf-8');
      res.setHeader(
        'Cache-Control',
        'public, s-maxage=3600, stale-while-revalidate=86400'
      );

      // ✅ Write XML directly
      res.end(sitemapXml);

      return { props: {} };
    } catch (error) {
      console.error('🚨 Failed to render sitemap.xml:', error);

      // Graceful fallback: return minimal sitemap instead of a 500
      const fallbackXml = buildFallbackSitemap(baseUrl);

      res.statusCode = 200;
      res.setHeader('Content-Type', 'application/xml; charset=utf-8');
      res.setHeader(
        'Cache-Control',
        'public, s-maxage=600, stale-while-revalidate=3600'
      );
      res.end(fallbackXml);

      return { props: { fallback: true } };
    }
  };
}

export const getServerSideProps = serverSidePropsFunction;

// 🪶 This component renders nothing (response is already sent in getServerSideProps)
const Sitemap = () => null;

export default Sitemap;
`;

const removeFileIfExists = (filePath, label) => {
  if (existsSync(filePath)) {
    try {
      unlinkSync(filePath);
      console.log(`🧹 Removed existing ${label}: ${filePath}`);
    } catch (error) {
      console.warn(`⚠️  Failed to remove ${label}: ${filePath}`, error);
    }
  }
};

async function setupServerSideSitemap() {
  console.log('\n🔧 Setting up server-side sitemap...');
  console.log('   SEO is enabled - sitemap will be generated server-side\n');

  // Ensure static sitemap file is removed to avoid conflicts
  removeFileIfExists(PUBLIC_SITEMAP_PATH, 'static sitemap (public/sitemap.xml)');

  // Check if sitemap.xml.js already exists
  if (existsSync(SITEMAP_PAGE_PATH)) {
    // Read existing file to check if it needs updating
    const existingContent = readFileSync(SITEMAP_PAGE_PATH, 'utf-8');
    
    // Check if it already has the conditional pattern
    if (existingContent.includes('let serverSidePropsFunction = null') && 
        existingContent.includes('if (process.env.NEXT_PUBLIC_ENABLE_SEO === "true")')) {
      console.log('✅ sitemap.xml.js already exists with correct pattern');
      console.log('   No changes needed.\n');
      return;
    } else {
      console.log('⚠️  sitemap.xml.js exists but needs updating');
      console.log('   Updating to use conditional pattern...\n');
    }
  } else {
    console.log('📝 Creating sitemap.xml.js for server-side rendering...\n');
  }

  // Write the server-side sitemap file
  writeFileSync(SITEMAP_PAGE_PATH, SERVER_SIDE_SITEMAP_TEMPLATE, 'utf-8');
  console.log('✅ Server-side sitemap file created/updated!');
  console.log(`📁 Location: ${SITEMAP_PAGE_PATH}\n`);
}

async function generateStaticSitemap() {
  console.log('\n🚀 Generating static sitemap...');
  console.log('   SEO is disabled - generating static sitemap.xml\n');

  try {
    // Ensure server-side sitemap page is removed to avoid conflicts
    removeFileIfExists(SITEMAP_PAGE_PATH, 'server-side sitemap page (src/pages/sitemap.xml.js)');

    // Check if BASE_URL is set
    const BASE_URL = process.env.NEXT_PUBLIC_WEB_URL;
    
    if (!BASE_URL) {
      console.warn('⚠️  WARNING: NEXT_PUBLIC_WEB_URL is not set!');
      console.warn('   Using default URL: https://e-demand-next-js.vercel.app\n');
    } else {
      console.log(`🔗 Base URL: ${BASE_URL}\n`);
    }

    // Import and run the static sitemap generation
    console.log('📦 Importing sitemap builder...');
    const { generateSitemapXml } = await import('../src/utils/sitemapBuilder.js');
    
    console.log('🔄 Generating sitemap XML...');
    const sitemapXml = await generateSitemapXml({ 
      baseUrl: BASE_URL || 'https://e-demand-next-js.vercel.app' 
    });

    // Ensure public directory exists
    const publicDir = join(__dirname, '..', 'public');
    
    console.log('💾 Writing sitemap to file...');
    writeFileSync(PUBLIC_SITEMAP_PATH, sitemapXml, 'utf-8');

    console.log('✅ Static sitemap generated successfully!');
    console.log(`📁 Location: ${PUBLIC_SITEMAP_PATH}`);
    console.log(`📊 File size: ${(sitemapXml.length / 1024).toFixed(2)} KB\n`);
  } catch (error) {
    console.error('\n❌ Error generating static sitemap:');
    console.error('   Message:', error.message);
    console.error('   Stack:', error.stack);
    console.error('\n');
    process.exit(1);
  }
}

async function run() {
  try {
    console.log('🔍 Checking SEO configuration...');
    console.log(`   NEXT_PUBLIC_ENABLE_SEO: ${process.env.NEXT_PUBLIC_ENABLE_SEO || 'not set'}`);
    console.log(`   SEO Enabled: ${SEO_ENABLED}\n`);
    
    if (SEO_ENABLED) {
      // SEO is enabled: ensure server-side sitemap file exists
      await setupServerSideSitemap();
    } else {
      // SEO is disabled: generate static sitemap
      await generateStaticSitemap();
    }
    
    console.log('✅ Sitemap setup completed successfully!\n');
  } catch (error) {
    console.error('\n❌ Error in sitemap setup:');
    console.error('   Message:', error.message);
    console.error('   Stack:', error.stack);
    console.error('\n');
    process.exit(1);
  }
}

run();

