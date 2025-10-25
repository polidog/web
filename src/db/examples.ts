/**
 * Drizzle ORM Usage Examples
 * このファイルは実際のコードではなく、使い方のリファレンスです
 */

import { db } from "@/db";
import { users, type NewUser } from "@/db/schema";
import { eq, and, or, like, gt, lt } from "drizzle-orm";

/**
 * 基本的なCRUD操作
 */

// ==================== CREATE ====================

// 単一レコード挿入
async function createUser() {
	const [newUser] = await db
		.insert(users)
		.values({
			name: "John Doe",
			email: "john@example.com",
		})
		.returning();

	return newUser;
}

// 複数レコード挿入
async function createMultipleUsers() {
	const newUsers = await db
		.insert(users)
		.values([
			{ name: "Alice", email: "alice@example.com" },
			{ name: "Bob", email: "bob@example.com" },
		])
		.returning();

	return newUsers;
}

// ==================== READ ====================

// 全件取得
async function getAllUsers() {
	const allUsers = await db.select().from(users);
	return allUsers;
}

// 条件付き取得
async function getUserById(id: number) {
	const [user] = await db.select().from(users).where(eq(users.id, id));
	return user;
}

// メールアドレスで検索
async function getUserByEmail(email: string) {
	const [user] = await db.select().from(users).where(eq(users.email, email));
	return user;
}

// 複数条件 (AND)
async function getUserByNameAndEmail(name: string, email: string) {
	const [user] = await db
		.select()
		.from(users)
		.where(and(eq(users.name, name), eq(users.email, email)));
	return user;
}

// 複数条件 (OR)
async function getUserByNameOrEmail(name: string, email: string) {
	const results = await db
		.select()
		.from(users)
		.where(or(eq(users.name, name), eq(users.email, email)));
	return results;
}

// LIKE検索
async function searchUsersByName(searchTerm: string) {
	const results = await db
		.select()
		.from(users)
		.where(like(users.name, `%${searchTerm}%`));
	return results;
}

// ページネーション
async function getUsersPaginated(page = 1, pageSize = 10) {
	const offset = (page - 1) * pageSize;
	const results = await db.select().from(users).limit(pageSize).offset(offset);
	return results;
}

// 並び替え
async function getUsersSorted() {
	const results = await db
		.select()
		.from(users)
		.orderBy(users.createdAt); // desc(users.createdAt) で降順
	return results;
}

// 特定カラムのみ取得
async function getUserNamesOnly() {
	const results = await db
		.select({
			id: users.id,
			name: users.name,
		})
		.from(users);
	return results;
}

// ==================== UPDATE ====================

// 単一レコード更新
async function updateUser(id: number, name: string) {
	const [updated] = await db
		.update(users)
		.set({ name })
		.where(eq(users.id, id))
		.returning();
	return updated;
}

// 複数カラム更新
async function updateUserFull(id: number, data: Partial<NewUser>) {
	const [updated] = await db
		.update(users)
		.set(data)
		.where(eq(users.id, id))
		.returning();
	return updated;
}

// ==================== DELETE ====================

// 単一レコード削除
async function deleteUser(id: number) {
	await db.delete(users).where(eq(users.id, id));
}

// 条件付き削除
async function deleteUsersByEmail(email: string) {
	await db.delete(users).where(eq(users.email, email));
}

// ==================== ADVANCED ====================

// カウント
async function countUsers() {
	const [{ count }] = await db
		.select({ count: db.$count(users) })
		.from(users);
	return count;
}

// トランザクション
async function createUserWithTransaction() {
	await db.transaction(async (tx) => {
		// トランザクション内で複数の操作
		const [user] = await tx
			.insert(users)
			.values({ name: "Test", email: "test@example.com" })
			.returning();

		// 何か条件に応じてロールバック
		if (!user) {
			tx.rollback();
		}
	});
}

// 生SQLクエリ (必要な場合のみ使用)
async function rawSqlQuery() {
	// 型安全性が失われるため、通常は避けるべき
	const result = await db.execute(
		"SELECT * FROM users WHERE email = ?",
		"test@example.com",
	);
	return result;
}

/**
 * Next.js App Router での使い方
 */

// Server Component での使用例
export async function UsersServerComponent() {
	const allUsers = await db.select().from(users);

	return (
		<div>
			<h1>Users</h1>
			<ul>
				{allUsers.map((user) => (
					<li key={user.id}>
						{user.name} ({user.email})
					</li>
				))}
			</ul>
		</div>
	);
}

// Server Action での使用例
export async function createUserAction(formData: FormData) {
	"use server";

	const name = formData.get("name") as string;
	const email = formData.get("email") as string;

	const [newUser] = await db
		.insert(users)
		.values({ name, email })
		.returning();

	return newUser;
}

// Route Handler での使用例 (既に src/app/api/users/route.ts に実装済み)
