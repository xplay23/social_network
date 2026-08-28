import { supabase } from '../lib/supabase'

export const authService = {
  getSession: () => supabase.auth.getSession(),
  onAuthStateChange: (callback: Parameters<typeof supabase.auth.onAuthStateChange>[0]) =>
    supabase.auth.onAuthStateChange(callback),
  signIn: (email: string, password: string) =>
    supabase.auth.signInWithPassword({ email, password }),
  signUp: (email: string, password: string, metadata: { name: string; username: string }) =>
    supabase.auth.signUp({ email, password, options: { data: metadata } }),
  signOut: () => supabase.auth.signOut(),
}
