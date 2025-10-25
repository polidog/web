import { db } from "@/db";
import { users, type NewUser } from "@/db/schema";
import { eq } from "drizzle-orm";
import { NextResponse } from "next/server";

/**
 * GET /api/users
 * Fetch all users from database
 */
export async function GET() {
	try {
		const allUsers = await db.select().from(users);
		return NextResponse.json({ success: true, data: allUsers });
	} catch (error) {
		console.error("Error fetching users:", error);
		return NextResponse.json(
			{ success: false, error: "Failed to fetch users" },
			{ status: 500 },
		);
	}
}

/**
 * POST /api/users
 * Create a new user
 */
export async function POST(request: Request) {
	try {
		const body = (await request.json()) as NewUser;

		// Validate required fields
		if (!body.name || !body.email) {
			return NextResponse.json(
				{ success: false, error: "Name and email are required" },
				{ status: 400 },
			);
		}

		// Insert new user
		const [newUser] = await db
			.insert(users)
			.values({
				name: body.name,
				email: body.email,
			})
			.returning();

		return NextResponse.json(
			{ success: true, data: newUser },
			{ status: 201 },
		);
	} catch (error) {
		console.error("Error creating user:", error);
		return NextResponse.json(
			{ success: false, error: "Failed to create user" },
			{ status: 500 },
		);
	}
}

/**
 * DELETE /api/users
 * Delete a user by ID (query parameter)
 */
export async function DELETE(request: Request) {
	try {
		const { searchParams } = new URL(request.url);
		const id = searchParams.get("id");

		if (!id) {
			return NextResponse.json(
				{ success: false, error: "User ID is required" },
				{ status: 400 },
			);
		}

		await db.delete(users).where(eq(users.id, Number.parseInt(id)));

		return NextResponse.json({
			success: true,
			message: "User deleted successfully",
		});
	} catch (error) {
		console.error("Error deleting user:", error);
		return NextResponse.json(
			{ success: false, error: "Failed to delete user" },
			{ status: 500 },
		);
	}
}
