CREATE TABLE "User" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "githubId" INTEGER NOT NULL,
  "login" TEXT NOT NULL,
  "name" TEXT,
  "avatarUrl" TEXT,
  "role" TEXT NOT NULL DEFAULT 'admin',
  "createdAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ("githubId")
);
CREATE TABLE "Post" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "kind" TEXT NOT NULL DEFAULT 'post',
  "path" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT NOT NULL,
  "html" TEXT NOT NULL,
  "excerpt" TEXT,
  "eyecatch" TEXT,
  "disqusId" TEXT,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "publishedAt" TEXT,
  "createdAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "authorId" INTEGER,
  UNIQUE ("path"),
  FOREIGN KEY ("authorId") REFERENCES "User" ("id")
);
CREATE TABLE "Tag" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  UNIQUE ("slug")
);
CREATE TABLE "Category" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  UNIQUE ("slug")
);
CREATE TABLE "_PostToTag" (
  "A" INTEGER NOT NULL,
  "B" INTEGER NOT NULL,
  PRIMARY KEY ("A", "B"),
  FOREIGN KEY ("A") REFERENCES "Post" ("id"),
  FOREIGN KEY ("B") REFERENCES "Tag" ("id")
);
CREATE TABLE "_CategoryToPost" (
  "A" INTEGER NOT NULL,
  "B" INTEGER NOT NULL,
  PRIMARY KEY ("A", "B"),
  FOREIGN KEY ("A") REFERENCES "Category" ("id"),
  FOREIGN KEY ("B") REFERENCES "Post" ("id")
);
