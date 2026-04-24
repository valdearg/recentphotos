<template>
    <div class="share-panel">
        <div class="share-header">
            <strong>Share links</strong>
            <button type="button" @click="$emit('close')">×</button>
        </div>

        <div v-if="loading">Loading…</div>

        <div v-else>
            <div v-if="shares.length === 0" class="empty">
                No public links yet.
            </div>

            <div v-for="share in shares" :key="share.id" class="share-row">
                <input :value="share.url" readonly @click="selectInput($event)" @dblclick="copyLink(share.url, $event)">
                <button type="button" @click="copyLink(share.url)">Copy</button>
                <button type="button" @click="deleteShare(share.id)">Delete</button>
            </div>

            <button type="button" class="create-button" @click="createPublicLink" :disabled="busy">
                Create public link
            </button>

            <div v-if="message" class="message">{{ message }}</div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios';

export default {
    name: 'SharePanel',

    props: {
        item: { type: Object, required: true },
    },

    data() {
        return {
            shares: [],
            loading: false,
            busy: false,
            message: '',
            messageTimer: null,
        }
    },

    mounted() {
        this.loadShares()
    },

    watch: {
        item() {
            this.loadShares()
        },
    },

    methods: {
        ocsPath() {
            if (!this.item?.path) return ''
            return this.item.path.replace(/^\/[^/]+\/files/, '') || this.item.path
        },

        async loadShares() {
            this.loading = true
            this.message = ''

            try {
                const response = await axios.get('/ocs/v2.php/apps/files_sharing/api/v1/shares', {
                    headers: {
                        'OCS-APIRequest': 'true',
                        Accept: 'application/json',
                    },
                    params: {
                        path: this.ocsPath(),
                        reshares: true,
                    },
                })

                const data = response.data?.ocs?.data || []
                this.shares = data.filter(share => Number(share.share_type) === 3)
            } catch (e) {
                this.message = 'Could not load shares.'
            } finally {
                this.loading = false
            }
        },

        async createPublicLink() {
            this.busy = true
            this.message = ''

            try {
                const body = new URLSearchParams()
                body.append('path', this.ocsPath())
                body.append('shareType', '3')
                body.append('permissions', '1')

                await axios.post('/ocs/v2.php/apps/files_sharing/api/v1/shares', body, {
                    headers: {
                        'OCS-APIRequest': 'true',
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                })

                await this.loadShares()
            } catch (e) {
                this.message = 'Could not create share link.'
            } finally {
                this.busy = false
            }
        },

        async deleteShare(id) {
            this.busy = true
            this.message = ''

            try {
                await axios.delete(`/ocs/v2.php/apps/files_sharing/api/v1/shares/${id}`, {
                    headers: {
                        'OCS-APIRequest': 'true',
                        Accept: 'application/json',
                    },
                })

                await this.loadShares()
            } catch (e) {
                this.message = 'Could not delete share.'
            } finally {
                this.busy = false
            }
        },

        async copyLink(url, e) {
            try {
                await navigator.clipboard.writeText(url)
                this.setMessage('Copied link ✓')

                if (e?.target) {
                    const el = e.target
                    el.classList.add('copied')
                    setTimeout(() => el.classList.remove('copied'), 300)
                }
            } catch (e) {
                this.setMessage('Could not copy link.')
            }
        },

        selectInput(e) {
            const el = e.target
            if (!el) return

            el.focus()

            try {
                el.setSelectionRange(0, el.value.length)
            } catch {
                el.select()
            }
        },

        setMessage(text) {
            this.message = text

            if (this.messageTimer) {
                clearTimeout(this.messageTimer)
            }

            this.messageTimer = setTimeout(() => {
                this.message = ''
                this.messageTimer = null
            }, 1200)
        },

        beforeDestroy() {
            if (this.messageTimer) {
                clearTimeout(this.messageTimer)
            }
        },
    },
}
</script>

<style scoped>
.share-panel {
    position: absolute;
    top: 76px;
    right: 20px;
    width: min(520px, calc(100vw - 40px));
    padding: 12px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.78);
    color: white;
    z-index: 100002;
}

.share-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.share-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 6px;
    margin-bottom: 8px;
    align-items: center;
}

input {
    width: 100%;
    min-width: 0;
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.08);
    color: white;
    box-sizing: border-box;
    font-family: monospace;
    font-size: 12px;
}

input.copied {
    outline: 2px solid #4ade80;
}

button {
    height: 30px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.08);
    color: white;
    cursor: pointer;
}

button:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.18);
}

button:disabled {
    opacity: 0.5;
    cursor: default;
}

.create-button {
    margin-top: 6px;
}

.empty,
.message {
    opacity: 0.8;
    margin: 8px 0;
}
</style>