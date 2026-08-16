CREATE TABLE "OauthClient" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "clientId" TEXT NOT NULL,
  "clientName" TEXT NOT NULL,
  "redirectUris" TEXT NOT NULL,
  "tokenEndpointAuthMethod" TEXT NOT NULL DEFAULT 'none',
  "clientSecretHash" TEXT,
  "createdAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ("clientId")
);
CREATE TABLE "OauthAuthCode" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "codeHash" TEXT NOT NULL,
  "clientId" TEXT NOT NULL,
  "redirectUri" TEXT NOT NULL,
  "codeChallenge" TEXT NOT NULL,
  "scope" TEXT NOT NULL,
  "resource" TEXT NOT NULL,
  "expiresAt" TEXT NOT NULL,
  "usedAt" TEXT,
  "createdAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ("codeHash")
);
CREATE TABLE "OauthToken" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "accessTokenHash" TEXT NOT NULL,
  "refreshTokenHash" TEXT,
  "clientId" TEXT NOT NULL,
  "scope" TEXT NOT NULL,
  "resource" TEXT NOT NULL,
  "accessExpiresAt" TEXT NOT NULL,
  "refreshExpiresAt" TEXT,
  "revokedAt" TEXT,
  "createdAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ("accessTokenHash"),
  UNIQUE ("refreshTokenHash")
);
