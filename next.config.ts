import { fileURLToPath } from "node:url";
import createJiti from "jiti";
import type { NextConfig } from "next";

// Import env files to validate at build time
const jiti = createJiti(fileURLToPath(import.meta.url));
jiti("./src/env/server.ts");

const nextConfig: NextConfig = {
	/* config options here */
	reactCompiler: true,
};

export default nextConfig;
