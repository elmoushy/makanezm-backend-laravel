<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    use ApiResponseTrait;

    /**
     * Store a new contact message (public endpoint).
     */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $contactMessage = ContactMessage::create([
            'name' => $request->name,
            'email' => $request->email,
            'message' => $request->message,
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رسالتك بنجاح',
            'data' => $contactMessage,
        ], 201);
    }

    /**
     * Get all contact messages (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $query = ContactMessage::query()->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        $messages = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Get a single contact message (admin only).
     */
    public function show(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        return response()->json([
            'success' => true,
            'data' => $contactMessage,
        ]);
    }

    /**
     * Mark a message as read (admin only).
     */
    public function markAsRead(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $contactMessage->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الرسالة',
            'data' => $contactMessage,
        ]);
    }

    /**
     * Mark a message as unread (admin only).
     */
    public function markAsUnread(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $contactMessage->update(['is_read' => false]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الرسالة',
            'data' => $contactMessage,
        ]);
    }

    /**
     * Delete a contact message (admin only).
     */
    public function destroy(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الرسالة بنجاح',
        ]);
    }

    /**
     * Get unread messages count (admin only).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $count = ContactMessage::where('is_read', false)->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }
}
