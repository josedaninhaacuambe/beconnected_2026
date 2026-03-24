<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-bc-light">Secções da Loja</h1>
        <p class="text-bc-muted text-sm mt-0.5">Organiza os teus produtos em secções para facilitar a navegação</p>
      </div>
    </div>

    <!-- Secções existentes -->
    <div class="space-y-3 mb-6">
      <div v-if="loading" v-for="i in 3" :key="i" class="skeleton h-16 rounded-xl"></div>

      <div v-else-if="sections.length === 0" class="card-african p-8 text-center text-bc-muted">
        <p class="text-3xl mb-2">🗂</p>
        <p>Ainda não criaste nenhuma secção.</p>
        <p class="text-xs mt-1">Cria secções como "Bebidas", "Frescos", "Promoções"...</p>
      </div>

      <div
        v-for="section in sections"
        :key="section.id"
        class="card-african p-4 flex items-center gap-4"
      >
        <!-- Emoji picker trigger -->
        <div class="relative">
          <button
            type="button"
            @click="toggleEmojiPicker(section.id)"
            class="w-12 h-12 rounded-xl bg-bc-surface-2 flex items-center justify-center text-2xl hover:bg-bc-gold/10 transition"
          >{{ section.icon }}</button>
          <div v-if="emojiPickerFor === section.id"
            class="absolute top-14 left-0 z-20 bg-bc-surface border border-bc-gold/30 rounded-xl p-3 shadow-xl grid grid-cols-5 gap-1 w-52">
            <button v-for="e in emojis" :key="e" type="button"
              @click="setEmoji(section, e)"
              class="text-xl hover:bg-bc-gold/10 rounded-lg p-1 transition">{{ e }}</button>
          </div>
        </div>

        <!-- Name (inline edit) -->
        <div class="flex-1">
          <input
            v-if="editingId === section.id"
            v-model="section.name"
            @blur="saveSection(section)"
            @keydown.enter="saveSection(section)"
            @keydown.escape="editingId = null"
            class="input-african text-sm py-1"
            autofocus
          />
          <div v-else @click="editingId = section.id" class="cursor-pointer">
            <p class="text-bc-light font-medium">{{ section.name }}</p>
            <p class="text-bc-muted text-xs">{{ section.products_count }} produto{{ section.products_count !== 1 ? 's' : '' }}</p>
          </div>
        </div>

        <!-- Toggle active -->
        <button
          type="button"
          @click="toggleActive(section)"
          :class="['w-8 h-8 rounded-lg flex items-center justify-center transition text-sm', section.is_active ? 'text-green-400 bg-green-400/10 hover:bg-green-400/20' : 'text-bc-muted bg-bc-surface-2 hover:bg-bc-surface']"
          :title="section.is_active ? 'Activa — clica para desactivar' : 'Inactiva — clica para activar'"
        >{{ section.is_active ? '✓' : '○' }}</button>

        <!-- Delete -->
        <button
          type="button"
          @click="deleteSection(section)"
          class="w-8 h-8 rounded-lg flex items-center justify-center text-bc-muted hover:text-red-400 hover:bg-red-900/20 transition"
        >🗑</button>
      </div>
    </div>

    <!-- Criar nova secção -->
    <div class="card-african p-5">
      <h2 class="text-bc-gold font-semibold mb-4">Nova Secção</h2>
      <div class="flex gap-3">
        <!-- Emoji -->
        <div class="relative">
          <button type="button" @click="toggleEmojiPicker('new')"
            class="w-12 h-12 rounded-xl bg-bc-surface-2 flex items-center justify-center text-2xl hover:bg-bc-gold/10 transition flex-shrink-0">
            {{ newSection.icon }}
          </button>
          <div v-if="emojiPickerFor === 'new'"
            class="absolute top-14 left-0 z-20 bg-bc-surface border border-bc-gold/30 rounded-xl p-3 shadow-xl grid grid-cols-5 gap-1 w-52">
            <button v-for="e in emojis" :key="e" type="button"
              @click="newSection.icon = e; emojiPickerFor = null"
              class="text-xl hover:bg-bc-gold/10 rounded-lg p-1 transition">{{ e }}</button>
          </div>
        </div>
        <input
          v-model="newSection.name"
          type="text"
          placeholder="Nome da secção (ex: Bebidas)"
          class="input-african flex-1"
          @keydown.enter.prevent="createSection"
        />
        <button
          type="button"
          @click="createSection"
          :disabled="!newSection.name.trim() || creating"
          class="btn-gold px-5 disabled:opacity-50"
        >{{ creating ? '...' : '+ Criar' }}</button>
      </div>
      <p v-if="createError" class="text-red-400 text-xs mt-2">{{ createError }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const sections = ref([])
const loading = ref(true)
const creating = ref(false)
const createError = ref('')
const editingId = ref(null)
const emojiPickerFor = ref(null)

const newSection = reactive({ name: '', icon: '📦' })

const emojis = ['📦','🛒','🍎','🥩','🥛','🍞','🧃','🥤','🧴','💊','👗','👟','📱','💻','🔨','🏗','🌱','🐾','🎮','📚','💄','🏋','🚗','🍽','🏪','⭐','🔥','💎','🎁','🧹']

function toggleEmojiPicker(id) {
  emojiPickerFor.value = emojiPickerFor.value === id ? null : id
}

async function setEmoji(section, emoji) {
  emojiPickerFor.value = null
  section.icon = emoji
  await axios.put(`/store/sections/${section.id}`, { icon: emoji })
}

async function loadSections() {
  loading.value = true
  try {
    const { data } = await axios.get('/store/sections')
    sections.value = data
  } finally {
    loading.value = false
  }
}

async function createSection() {
  if (!newSection.name.trim()) return
  creating.value = true
  createError.value = ''
  try {
    const { data } = await axios.post('/store/sections', { name: newSection.name.trim(), icon: newSection.icon })
    sections.value.push(data)
    newSection.name = ''
    newSection.icon = '📦'
  } catch (e) {
    createError.value = e.response?.data?.message || 'Erro ao criar secção.'
  } finally {
    creating.value = false
  }
}

async function saveSection(section) {
  editingId.value = null
  await axios.put(`/store/sections/${section.id}`, { name: section.name }).catch(() => {})
}

async function toggleActive(section) {
  section.is_active = !section.is_active
  await axios.put(`/store/sections/${section.id}`, { is_active: section.is_active }).catch(() => {
    section.is_active = !section.is_active
  })
}

async function deleteSection(section) {
  if (!confirm(`Eliminar a secção "${section.name}"? Os produtos não serão apagados.`)) return
  await axios.delete(`/store/sections/${section.id}`)
  sections.value = sections.value.filter(s => s.id !== section.id)
}

onMounted(loadSections)
</script>
