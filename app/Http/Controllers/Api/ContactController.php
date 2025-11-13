<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /**
     * Store a new contact message (Public API)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:50',
                'subject' => 'nullable|string|max:255',
                'message' => 'required|string',
            ]);

            $contact = Contact::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'subject' => $request->subject,
                'message' => $request->message,
                'status' => Contact::STATUS_PENDING,
            ]);

            return $this->successResponse([
                'contact' => $contact,
            ], 'Contact message submitted successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to submit contact message: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get all contact messages (Admin only)
     */
    public function index()
    {
        try {
            $contacts = Contact::orderByDesc('created_at')->get();

            return $this->successResponse([
                'contacts' => $contacts,
                'total' => $contacts->count(),
            ], 'Contacts retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve contacts: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a specific contact message (Admin only)
     */
    public function show($id)
    {
        try {
            $contact = Contact::find($id);

            if (!$contact) {
                return $this->notFoundResponse('Contact');
            }

            return $this->successResponse([
                'contact' => $contact,
            ], 'Contact retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve contact: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a contact message (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $contact = Contact::find($id);

            if (!$contact) {
                return $this->notFoundResponse('Contact');
            }

            $request->validate([
                'status' => 'sometimes|in:' . Contact::STATUS_PENDING . ',' . Contact::STATUS_RESOLVED,
                'admin_notes' => 'sometimes|nullable|string',
            ]);

            $updateData = [];

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            if ($request->has('admin_notes')) {
                $updateData['admin_notes'] = $request->admin_notes;
            }

            if (empty($updateData)) {
                return $this->errorResponse('No fields provided for update', 400);
            }

            $contact->update($updateData);

            return $this->successResponse([
                'contact' => $contact->fresh(),
            ], 'Contact updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update contact: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a contact message (Admin only)
     */
    public function destroy($id)
    {
        try {
            $contact = Contact::find($id);

            if (!$contact) {
                return $this->notFoundResponse('Contact');
            }

            $contact->delete();

            return $this->successResponse(null, 'Contact deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete contact: ' . $e->getMessage(),
                500
            );
        }
    }
}
