<x-app-layout>
    <x-slot name="header">
        {{ __('ثبت درخواست پشتیبانی جدید') }}
    </x-slot>

    <div class="p-6 bg-white rounded-lg shadow border">
         {{-- Display Validation Errors --}}
         @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-700 rounded-md text-sm">
                <p class="font-semibold">لطفا خطاهای زیر را برطرف کنید:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        {{-- Display Session Error --}}
        @if(session('error'))
             <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-700 rounded-md text-sm">
                 {{ session('error') }}
             </div>
        @endif

        <form method="POST" action="{{ route('tickets.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Subject --}}
                <div class="md:col-span-2">
                    <label for="subject" class="block text-sm font-medium text-gray-700">موضوع <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required maxlength="255"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('subject') border-red-500 @enderror">
                    @error('subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                 {{-- Priority --}}
                <div>
                     <label for="priority" class="block text-sm font-medium text-gray-700">اولویت <span class="text-red-500">*</span></label>
                     <select name="priority" id="priority" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('priority') border-red-500 @enderror">
                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>پایین</option>
                        <option value="medium" {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>متوسط</option> {{-- Default to medium --}}
                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>بالا</option>
                        <option value="critical" {{ old('priority') == 'critical' ? 'selected' : '' }}>بحرانی</option>
                    </select>
                    @error('priority') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                 {{-- Empty div for grid alignment or other fields --}}
                 <div></div>

                 {{-- Message --}}
                <div class="md:col-span-2">
                    <label for="message" class="block text-sm font-medium text-gray-700">متن درخواست <span class="text-red-500">*</span></label>
                    <textarea name="message" id="message" rows="6" required
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('message') border-red-500 @enderror">{{ old('message') }}</textarea>
                    @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Optional: File Upload --}}
                {{--
                <div class="md:col-span-2">
                    <label for="attachment" class="block text-sm font-medium text-gray-700">فایل پیوست (اختیاری)</label>
                    <input type="file" name="attachment" id="attachment"
                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 @error('attachment') border-red-500 @enderror">
                    @error('attachment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                --}}

            </div>

            {{-- Form Actions --}}
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end items-center">
                <a href="{{ route('tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-800 mr-4">
                    انصراف
                </a>
                <button type="submit" class="btn-blue-app">
                    <i class="fas fa-paper-plane mr-1 ml-1"></i>
                    ارسال درخواست
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
