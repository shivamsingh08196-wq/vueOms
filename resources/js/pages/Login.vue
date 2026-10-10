<script setup>
import { useField, useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/zod';
import * as z from 'zod';
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';
import { ref } from 'vue';

const schema = toTypedSchema(
  z.object({
    email: z.string().email('Invalid email address'),
    password: z.string().min(6, 'Password must be at least 6 characters'),
  })
);

const { handleSubmit, errors } = useForm({
  validationSchema: schema,
});

const { value: email } = useField('email');
const { value: password } = useField('password');

const auth = useAuthStore();
const router = useRouter();
const loginError = ref('');

const onSubmit = handleSubmit(async (values) => {
  try {
    await auth.login(values);
    router.push('/dashboard');
  } catch (err) {
    loginError.value = 'Failed to authenticate.';
  }
});
</script>

<template>
  <div class="flex min-h-screen flex-1 flex-col justify-center px-6 py-12 lg:px-8 bg-[var(--color-paper)]">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
      <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-[var(--color-ink)]">Sign in to your account</h2>
    </div>

    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm bg-white p-8 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)]">
      <form class="space-y-6" @submit="onSubmit">
        <div>
          <label for="email" class="block text-sm font-medium leading-6 text-[var(--color-ink)]">Email address</label>
          <div class="mt-2">
            <input id="email" v-model="email" type="email" autocomplete="email" class="block w-full rounded-md border-0 py-1.5 text-[var(--color-ink)] shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-[var(--color-amber)] sm:text-sm sm:leading-6" />
            <span class="text-red-500 text-xs mt-1">{{ errors.email }}</span>
          </div>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium leading-6 text-[var(--color-ink)]">Password</label>
          <div class="mt-2">
            <input id="password" v-model="password" type="password" autocomplete="current-password" class="block w-full rounded-md border-0 py-1.5 text-[var(--color-ink)] shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-[var(--color-amber)] sm:text-sm sm:leading-6" />
            <span class="text-red-500 text-xs mt-1">{{ errors.password }}</span>
          </div>
        </div>
        
        <div v-if="loginError" class="text-red-500 text-sm text-center">{{ loginError }}</div>

        <div>
          <button type="submit" class="flex w-full justify-center rounded-md bg-[var(--color-evergreen)] px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-[var(--color-teal)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)]">Sign in</button>
        </div>
      </form>
    </div>
  </div>
</template>
