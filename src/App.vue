<script setup lang="ts">
import type { Session } from '@supabase/supabase-js'
import { computed, onMounted, ref, watch } from 'vue'
import { isSupabaseConfigured, supabase } from './lib/supabase'
import { authService } from './services/auth.service'
import { profilesService } from './services/profiles.service'
import { socialFriendsService } from './services/socialFriends.service'
import { socialLikesService } from './services/socialLikes.service'
import { socialPostsService } from './services/socialPosts.service'

type Page = 'feed' | 'search' | 'create' | 'friends' | 'profile'
type Profile = {
  id: string
  display_name: string | null
  username: string | null
  avatar_url: string | null
}
type Friendship = {
  id: string
  sender_id: string
  receiver_id: string
  status: 'pending' | 'accepted' | 'declined'
  sender?: Profile
  receiver?: Profile
}
type Post = {
  id: string
  authorId: string
  author: Profile
  createdAt: string
  text: string
  imageUrl: string | null
  likes: number
  comments: number
  liked: boolean
}

const navItems: { id: Page; label: string; icon: string }[] = [
  { id: 'feed', label: 'Лента', icon: '⌂' },
  { id: 'search', label: 'Поиск', icon: '⌕' },
  { id: 'create', label: 'Создать', icon: '+' },
  { id: 'friends', label: 'Друзья', icon: '♧' },
  { id: 'profile', label: 'Профиль', icon: '○' },
]
const initialized = ref(false),
  loading = ref(false),
  publishing = ref(false)
const session = ref<Session | null>(null),
  profile = ref<Profile | null>(null)
const posts = ref<Post[]>([]),
  friendships = ref<Friendship[]>([]),
  searchResults = ref<Profile[]>([])
const activePage = ref<Page>('feed'),
  draft = ref(''),
  searchQuery = ref(''),
  errorMessage = ref('')
const authMode = ref<'login' | 'register'>('login'),
  authError = ref('')
const email = ref(''),
  password = ref(''),
  registerName = ref(''),
  registerUsername = ref('')
let searchTimer: ReturnType<typeof setTimeout> | undefined

const user = computed(() => session.value?.user ?? null)
const displayName = computed(
  () => profile.value?.display_name || user.value?.email?.split('@')[0] || 'Пользователь',
)
const handle = computed(() =>
  profile.value?.username ? `@${profile.value.username}` : user.value?.email || '',
)
const initials = computed(() => getInitials(displayName.value))
const pageTitle = computed(
  () => navItems.find((item) => item.id === activePage.value)?.label || 'Лента',
)
const incoming = computed(() =>
  friendships.value.filter(
    (item) => item.status === 'pending' && item.receiver_id === user.value?.id,
  ),
)
const friends = computed(() => friendships.value.filter((item) => item.status === 'accepted'))
const ownPosts = computed(
  () => posts.value.filter((post) => post.authorId === user.value?.id).length,
)

function getInitials(value?: string | null) {
  return (
    (value || '?')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || '?'
  )
}
function otherProfile(item: Friendship) {
  return item.sender_id === user.value?.id ? item.receiver : item.sender
}
function formatTime(value: string) {
  const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60000)
  if (minutes < 1) return 'только что'
  if (minutes < 60) return `${minutes} мин. назад`
  if (minutes < 1440) return `${Math.floor(minutes / 60)} ч. назад`
  return new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'short' }).format(new Date(value))
}
function selectPage(page: Page) {
  if (page === 'create') {
    activePage.value = 'feed'
    requestAnimationFrame(() =>
      document.querySelector<HTMLTextAreaElement>('.composer__input')?.focus(),
    )
  } else activePage.value = page
}

function toggleAuthMode() {
  authMode.value = authMode.value === 'login' ? 'register' : 'login'
  authError.value = ''
}

async function loadData() {
  if (!user.value) return
  loading.value = true
  errorMessage.value = ''
  const [profileResult, postsResult, friendsResult] = await Promise.all([
    profilesService.getById(user.value.id),
    supabase
      .from('social_posts')
      .select(
        'id,author_id,content,image_url,created_at,author:profiles!social_posts_author_id_fkey(id,display_name,username,avatar_url),social_likes(user_id),social_comments(id)',
      )
      .order('created_at', { ascending: false }),
    supabase
      .from('social_friendships')
      .select(
        'id,sender_id,receiver_id,status,sender:profiles!social_friendships_sender_id_fkey(id,display_name,username,avatar_url),receiver:profiles!social_friendships_receiver_id_fkey(id,display_name,username,avatar_url)',
      )
      .or(`sender_id.eq.${user.value.id},receiver_id.eq.${user.value.id}`),
  ])
  const firstError = profileResult.error || postsResult.error || friendsResult.error
  if (firstError) errorMessage.value = firstError.message
  else {
    profile.value = profileResult.data as Profile
    posts.value = (postsResult.data || []).map((row: any) => ({
      id: row.id,
      authorId: row.author_id,
      author: row.author,
      createdAt: row.created_at,
      text: row.content,
      imageUrl: row.image_url,
      likes: row.social_likes?.length || 0,
      comments: row.social_comments?.length || 0,
      liked: row.social_likes?.some((like: any) => like.user_id === user.value?.id) || false,
    }))
    friendships.value = (friendsResult.data || []) as unknown as Friendship[]
  }
  loading.value = false
}

async function submitAuth() {
  loading.value = true
  authError.value = ''
  try {
    if (authMode.value === 'login') {
      const { data, error } = await authService.signIn(email.value.trim(), password.value)
      if (error) throw error
      session.value = data.session
    } else {
      const username = registerUsername.value.trim().toLowerCase()
      if (!/^[a-z0-9_]{3,30}$/.test(username))
        throw new Error('Ник: 3–30 символов, латиница, цифры или _.')
      const { data, error } = await authService.signUp(email.value.trim(), password.value, {
        name: registerName.value.trim(),
        username,
      })
      if (error) throw error
      session.value = data.session
      if (!data.session)
        authError.value = 'Подтвердите регистрацию по ссылке в письме, затем войдите.'
    }
    if (session.value) await loadData()
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Ошибка авторизации'
    authError.value = /already|registered|exists/i.test(message)
      ? 'Аккаунт уже существует. Войдите в него.'
      : message
  }
  loading.value = false
}
async function logout() {
  await authService.signOut()
  session.value = null
  profile.value = null
  posts.value = []
  friendships.value = []
}
async function publish() {
  const text = draft.value.trim()
  if (!text || !user.value || publishing.value) return
  publishing.value = true
  const { error } = await socialPostsService.create(user.value.id, text)
  if (error) errorMessage.value = error.message
  else {
    draft.value = ''
    await loadData()
  }
  publishing.value = false
}
async function toggleLike(post: Post) {
  if (!user.value) return
  const previous = post.liked
  post.liked = !previous
  post.likes += previous ? -1 : 1
  const { error } = previous
    ? await socialLikesService.remove(post.id, user.value.id)
    : await socialLikesService.add(post.id, user.value.id)
  if (error) {
    post.liked = previous
    post.likes += previous ? 1 : -1
    errorMessage.value = error.message
  }
}
async function accept(item: Friendship) {
  const { error } = await socialFriendsService.setStatus(item.id, 'accepted')
  if (error) errorMessage.value = error.message
  else await loadData()
}
async function search() {
  const term = searchQuery.value.trim().replace(/[,()%]/g, '')
  if (!term) {
    searchResults.value = []
    return
  }
  const { data, error } = await supabase
    .from('profiles')
    .select('id,display_name,username,avatar_url')
    .or(`display_name.ilike.%${term}%,username.ilike.%${term}%`)
    .limit(20)
  if (error) errorMessage.value = error.message
  else searchResults.value = (data || []).filter((item) => item.id !== user.value?.id) as Profile[]
}
watch(searchQuery, () => {
  activePage.value = 'search'
  clearTimeout(searchTimer)
  searchTimer = setTimeout(search, 400)
})
onMounted(async () => {
  if (isSupabaseConfigured) {
    const { data } = await authService.getSession()
    session.value = data.session
    if (session.value) await loadData()
    authService.onAuthStateChange(async (_event, value) => {
      session.value = value
    })
  }
  initialized.value = true
})
</script>

<template>
  <div v-if="!initialized" class="status-screen">
    <span class="status-screen__loader"></span>
    <p class="status-screen__text">Загрузка…</p>
  </div>
  <div v-else-if="!isSupabaseConfigured" class="status-screen">
    <h1 class="status-screen__title">Supabase не настроен</h1>
    <p class="status-screen__text">Заполните переменные в .env.</p>
  </div>
  <main v-else-if="!session" class="auth-page">
    <section class="auth-card">
      <div class="auth-card__brand">
        <span class="brand__mark">c</span
        ><span class="brand__name brand__name--visible">circle</span>
      </div>
      <h1 class="auth-card__title">
        {{ authMode === 'login' ? 'С возвращением' : 'Создать аккаунт' }}
      </h1>
      <p class="auth-card__subtitle">Общий аккаунт Supabase</p>
      <form class="auth-form" @submit.prevent="submitAuth">
        <label v-if="authMode === 'register'" class="auth-form__field"
          ><span class="auth-form__label">Имя</span
          ><input
            v-model="registerName"
            class="auth-form__input"
            title="Введите ваше имя"
            required
            maxlength="80" /></label
        ><label v-if="authMode === 'register'" class="auth-form__field"
          ><span class="auth-form__label">Ник</span
          ><input
            v-model="registerUsername"
            class="auth-form__input"
            title="Введите уникальный ник"
            required /></label
        ><label class="auth-form__field"
          ><span class="auth-form__label">Email</span
          ><input
            v-model="email"
            class="auth-form__input"
            type="email"
            title="Введите email аккаунта"
            required /></label
        ><label class="auth-form__field"
          ><span class="auth-form__label">Пароль</span
          ><input
            v-model="password"
            class="auth-form__input"
            type="password"
            title="Введите пароль аккаунта"
            minlength="6"
            required
        /></label>
        <p v-if="authError" class="auth-form__error">{{ authError }}</p>
        <button
          class="button button--primary auth-form__submit"
          :title="authMode === 'login' ? 'Войти в аккаунт' : 'Создать новый аккаунт'"
          :disabled="loading"
        >
          {{ loading ? 'Подождите…' : authMode === 'login' ? 'Войти' : 'Зарегистрироваться' }}
        </button>
      </form>
      <button
        class="auth-card__switch"
        type="button"
        :title="authMode === 'login' ? 'Перейти к регистрации' : 'Перейти ко входу'"
        @click="toggleAuthMode"
      >
        {{ authMode === 'login' ? 'Создать аккаунт' : 'Уже есть аккаунт? Войти' }}
      </button>
    </section>
  </main>
  <div v-else class="app-shell">
    <header class="topbar">
      <div class="topbar__inner">
        <button class="brand" type="button" title="Открыть ленту" @click="selectPage('feed')">
          <span class="brand__mark">c</span><span class="brand__name">circle</span></button
        ><label class="topbar__search"
          ><span class="topbar__search-icon">⌕</span
          ><input
            v-model="searchQuery"
            class="topbar__search-input"
            type="search"
            title="Найти пользователя по имени или нику"
            placeholder="Поиск людей" /></label
        ><button
          class="topbar__avatar avatar avatar--small"
          type="button"
          title="Открыть мой профиль"
          @click="selectPage('profile')"
        >
          <img
            v-if="profile?.avatar_url"
            class="avatar__image"
            :src="profile.avatar_url"
            alt=""
          /><span v-else>{{ initials }}</span>
        </button>
      </div>
    </header>
    <div class="layout">
      <aside class="sidebar">
        <nav class="sidebar__nav">
          <button
            v-for="item in navItems.filter((item) => item.id !== 'create')"
            :key="item.id"
            class="sidebar__link"
            :class="{ 'sidebar__link--active': activePage === item.id }"
            type="button"
            :title="`Открыть раздел «${item.label}»`"
            @click="selectPage(item.id)"
          >
            <span class="sidebar__icon">{{ item.icon }}</span
            ><span class="sidebar__label">{{ item.label }}</span>
          </button>
        </nav>
        <div class="sidebar__user">
          <span class="avatar avatar--small">{{ initials }}</span
          ><span class="sidebar__user-copy"
            ><strong class="sidebar__user-name">{{ displayName }}</strong
            ><span class="sidebar__user-handle">{{ handle }}</span></span
          ><button class="sidebar__logout" type="button" title="Выйти из аккаунта" @click="logout">
            ↪
          </button>
        </div>
      </aside>
      <main class="main-content">
        <div class="page-heading">
          <div class="page-heading__copy">
            <span class="page-heading__eyebrow">Главная</span>
            <h1 class="page-heading__title">{{ pageTitle }}</h1>
          </div>
          <button
            class="page-heading__action"
            type="button"
            title="Выйти из аккаунта"
            @click="logout"
          >
            Выйти
          </button>
        </div>
        <p v-if="errorMessage" class="notice notice--error">{{ errorMessage }}</p>
        <template v-if="activePage === 'feed'"
          ><section class="composer">
            <span class="composer__avatar avatar">{{ initials }}</span>
            <div class="composer__body">
              <textarea
                v-model="draft"
                class="composer__input"
                maxlength="5000"
                rows="2"
                title="Написать текст новой публикации"
                placeholder="Что у вас нового?"
              ></textarea>
              <div class="composer__footer">
                <span class="composer__hint">{{ draft.length }}/5000</span
                ><button
                  class="button button--primary"
                  type="button"
                  title="Опубликовать запись в ленте"
                  :disabled="!draft.trim() || publishing"
                  @click="publish"
                >
                  {{ publishing ? 'Публикуем…' : 'Опубликовать' }}
                </button>
              </div>
            </div>
          </section>
          <div v-if="loading && !posts.length" class="empty-card">
            <p class="empty-card__text">Загружаем ленту…</p>
          </div>
          <div v-else-if="!posts.length" class="empty-card">
            <span class="empty-card__icon">✎</span>
            <h2 class="empty-card__title">Пока нет записей</h2>
            <p class="empty-card__text">Создайте первую публикацию.</p>
          </div>
          <div v-else class="feed">
            <article v-for="post in posts" :key="post.id" class="post-card">
              <div class="post-card__header">
                <span class="avatar"
                  ><img
                    v-if="post.author?.avatar_url"
                    class="avatar__image"
                    :src="post.author.avatar_url"
                    alt=""
                  /><span v-else>{{ getInitials(post.author?.display_name) }}</span></span
                >
                <div class="post-card__author">
                  <strong class="post-card__name">{{
                    post.author?.display_name || 'Пользователь'
                  }}</strong
                  ><span class="post-card__meta"
                    >{{ post.author?.username ? `@${post.author.username}` : '' }} ·
                    {{ formatTime(post.createdAt) }}</span
                  >
                </div>
              </div>
              <p class="post-card__text">{{ post.text }}</p>
              <img v-if="post.imageUrl" class="post-card__image" :src="post.imageUrl" alt="" />
              <div class="post-card__actions">
                <button
                  class="post-card__action"
                  :class="{ 'post-card__action--liked': post.liked }"
                  type="button"
                  :title="post.liked ? 'Убрать лайк с публикации' : 'Поставить лайк публикации'"
                  @click="toggleLike(post)"
                >
                  ♡ {{ post.likes }}</button
                ><button
                  class="post-card__action"
                  type="button"
                  title="Показать комментарии к публикации"
                >
                  ◯ {{ post.comments }}
                </button>
              </div>
            </article>
          </div></template
        >
        <section v-else-if="activePage === 'search'" class="people-card">
          <div v-if="!searchQuery" class="empty-card">
            <h2 class="empty-card__title">Найдите людей</h2>
            <p class="empty-card__text">Введите имя или ник.</p>
          </div>
          <div v-else-if="!searchResults.length" class="empty-card">
            <p class="empty-card__text">Ничего не найдено.</p>
          </div>
          <div v-else class="people-list">
            <div v-for="item in searchResults" :key="item.id" class="person-row">
              <span class="avatar">{{ getInitials(item.display_name) }}</span
              ><span class="person-row__copy"
                ><strong class="person-row__name">{{ item.display_name || 'Пользователь' }}</strong
                ><span class="person-row__handle">{{
                  item.username ? `@${item.username}` : ''
                }}</span></span
              >
            </div>
          </div>
        </section>
        <section v-else-if="activePage === 'friends'" class="people-card">
          <h2 class="people-card__title">Заявки</h2>
          <p v-if="!incoming.length" class="people-card__empty">Нет новых заявок</p>
          <div v-for="item in incoming" :key="item.id" class="person-row">
            <span class="avatar avatar--orange">{{ getInitials(item.sender?.display_name) }}</span
            ><span class="person-row__copy"
              ><strong class="person-row__name">{{ item.sender?.display_name }}</strong
              ><span class="person-row__handle">{{
                item.sender?.username ? `@${item.sender.username}` : ''
              }}</span></span
            ><button
              class="button button--primary"
              type="button"
              :title="`Принять заявку от ${item.sender?.display_name || 'пользователя'}`"
              @click="accept(item)"
            >
              Принять
            </button>
          </div>
          <h2 class="people-card__title people-card__title--spaced">Друзья</h2>
          <p v-if="!friends.length" class="people-card__empty">Список друзей пуст</p>
          <div v-for="item in friends" :key="item.id" class="person-row">
            <span class="avatar">{{ getInitials(otherProfile(item)?.display_name) }}</span
            ><span class="person-row__copy"
              ><strong class="person-row__name">{{ otherProfile(item)?.display_name }}</strong
              ><span class="person-row__handle">{{
                otherProfile(item)?.username ? `@${otherProfile(item)?.username}` : ''
              }}</span></span
            >
          </div>
        </section>
        <section v-else class="profile-card">
          <span class="profile-card__avatar avatar avatar--large">{{ initials }}</span>
          <h2 class="profile-card__name">{{ displayName }}</h2>
          <p class="profile-card__handle">{{ handle }}</p>
          <div class="profile-card__stats">
            <span class="profile-card__stat"
              ><strong class="profile-card__stat-value">{{ friends.length }}</strong> друзей</span
            ><span class="profile-card__stat"
              ><strong class="profile-card__stat-value">{{ ownPosts }}</strong> записей</span
            >
          </div>
        </section>
      </main>
      <aside class="rightbar">
        <section class="profile-summary">
          <span class="avatar avatar--large">{{ initials }}</span>
          <div class="profile-summary__copy">
            <strong class="profile-summary__name">{{ displayName }}</strong
            ><span class="profile-summary__handle">{{ handle }}</span>
          </div>
          <div class="profile-summary__stats">
            <span class="profile-summary__stat"
              ><strong class="profile-summary__stat-value">{{ friends.length }}</strong>
              друзей</span
            ><span class="profile-summary__stat"
              ><strong class="profile-summary__stat-value">{{ ownPosts }}</strong> записей</span
            >
          </div>
          <button
            class="button button--secondary"
            type="button"
            title="Открыть мой профиль"
            @click="selectPage('profile')"
          >
            Открыть профиль
          </button>
        </section>
        <section class="requests">
          <div class="requests__header">
            <h2 class="requests__title">Заявки</h2>
            <button
              class="requests__all"
              type="button"
              title="Показать все заявки в друзья"
              @click="selectPage('friends')"
            >
              Все ({{ incoming.length }})
            </button>
          </div>
          <p v-if="!incoming.length" class="requests__empty">Новых заявок нет</p>
          <div v-for="item in incoming.slice(0, 3)" :key="item.id" class="requests__person">
            <span class="avatar avatar--small avatar--orange">{{
              getInitials(item.sender?.display_name)
            }}</span
            ><span class="requests__copy"
              ><strong class="requests__name">{{ item.sender?.display_name }}</strong
              ><span class="requests__mutual">{{
                item.sender?.username ? `@${item.sender.username}` : ''
              }}</span></span
            ><button
              class="requests__accept"
              type="button"
              :title="`Принять заявку от ${item.sender?.display_name || 'пользователя'}`"
              @click="accept(item)"
            >
              +
            </button>
          </div>
        </section>
      </aside>
    </div>
    <nav class="bottom-nav">
      <button
        v-for="item in navItems"
        :key="item.id"
        class="bottom-nav__item"
        :class="{
          'bottom-nav__item--active': activePage === item.id,
          'bottom-nav__item--create': item.id === 'create',
        }"
        type="button"
        :title="`Открыть раздел «${item.label}»`"
        @click="selectPage(item.id)"
      >
        <span class="bottom-nav__icon">{{ item.icon }}</span
        ><span class="bottom-nav__label">{{ item.label }}</span>
      </button>
    </nav>
  </div>
</template>
