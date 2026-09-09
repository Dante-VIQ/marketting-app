{{-- resources/views/admin/contacts.blade.php --}}
@extends('layouts.app')

@section('title', 'Contact Messages')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-white mb-6">Contact Messages</h1>
    <div class="overflow-x-auto bg-slate-900/50 rounded-xl border border-slate-800">
        <table class="w-full text-sm text-left text-slate-300">
            <thead class="text-xs uppercase bg-slate-800/50 text-slate-400">
                <tr>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Message</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contacts as $contact)
                <tr class="border-b border-slate-800/50 hover:bg-slate-800/30">
                    <td class="px-6 py-4 font-medium text-white">{{ $contact->name }}</td>
                    <td class="px-6 py-4">{{ $contact->email }}</td>
                    <td class="px-6 py-4 max-w-xs truncate">{{ $contact->message }}</td>
                    <td class="px-6 py-4">
                        @if($contact->read)
                            <span class="px-2 py-1 text-xs rounded-full bg-green-900/50 text-green-300 border border-green-700/50">Read</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-900/50 text-yellow-300 border border-yellow-700/50">Unread</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">{{ $contact->created_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $contacts->links() }}
    </div>
</div>
@endsection