@php
  // Safe JSON for embedding in a data-attribute (no quotes breaking)
  $payload = json_encode([
    'id'            => $c->id,
    'code'          => (string)$c->code,
    'name'          => (string)$c->name,
    'email'         => (string)$c->email,
    'phone'         => (string)$c->phone,
    'billing_terms' => (string)$c->billing_terms,
  ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
@endphp

<tr id="row-{{ $c->id }}" class="hover:bg-gray-50">
  <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $c->code }}</td>
  <td class="px-4 py-2 font-medium text-gray-900">{{ $c->name }}</td>
  <td class="px-4 py-2 text-gray-700">{{ $c->email }}</td>
  <td class="px-4 py-2 text-gray-700">{{ $c->phone }}</td>
  <td class="px-4 py-2 text-gray-700">{{ $c->billing_terms }}</td>
  <td class="px-4 py-2 text-right whitespace-nowrap">
    <a href="{{ route('depot.clients.show', $c) }}"
       class="mr-3 inline-flex items-center text-indigo-600 hover:underline">View</a>

    <button type="button"
            class="mr-3 inline-flex items-center text-sky-600 hover:underline"
            data-client="{{ $payload }}"
            onclick="openEditFromRow(this)">
      Edit
    </button>

    <button type="button"
            class="inline-flex items-center text-red-600 hover:underline"
            data-id="{{ $c->id }}"
            onclick="confirmDelete(this)">
      Delete
    </button>
  </td>
</tr>
