<x-layouts::auth :title="__('Data privacy notice')">
    <div class="mx-auto w-full max-w-2xl">
        <div class="mb-8 text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-700 dark:text-teal-300">CVMS</p>
            <h1 class="mt-3 text-2xl font-semibold text-stone-900 dark:text-stone-100">Data privacy notice</h1>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-300">Please review this before accessing your child’s health information.</p>
        </div>

        <div class="space-y-5 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-8">
            <p class="text-sm leading-6 text-stone-700 dark:text-zinc-300">
                The Child Vaccination Management System (CVMS) collects and stores your account information and your child’s personal and vaccination information so authorized health workers can manage immunization services, maintain accurate records, and provide vaccination reminders.
            </p>
            <p class="text-sm leading-6 text-stone-700 dark:text-zinc-300">
                Your child’s information is sensitive personal information. It may be accessed by authorized clinic or public-health personnel who need it for immunization care, verification, reporting, and system administration. We apply access controls and other reasonable safeguards and do not disclose it for unrelated purposes.
            </p>
            <p class="text-sm leading-6 text-stone-700 dark:text-zinc-300">
                You may request access to or correction of your information, ask questions about its use, or raise a privacy concern through your clinic or the system’s privacy contact. Only children linked to your parent/guardian account are visible to you.
            </p>

            <form method="POST" action="{{ route('privacy.acknowledgment.store') }}" class="border-t border-stone-200 pt-5 dark:border-zinc-700">
                @csrf
                <label class="flex items-start gap-3 text-sm text-stone-700 dark:text-zinc-300">
                    <input name="acknowledged" type="checkbox" value="1" required class="mt-1 rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                    <span>I have read and understood this Data Privacy Notice, and I acknowledge how my child’s information is processed in CVMS.</span>
                </label>
                @error('acknowledged')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <button type="submit" class="mt-6 w-full rounded-lg bg-teal-700 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Continue to CVMS</button>
            </form>
        </div>
    </div>
</x-layouts::auth>
