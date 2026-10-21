import { fileURLToPath } from 'node:url'
import { mergeConfig } from 'vite'
import appConfig from '../../vite.config.js'

export default mergeConfig(appConfig, {
    build: {
        outDir: 'dist/phase3-check',
        emptyOutDir: true,
        rolldownOptions: {
            input: fileURLToPath(
                new URL('./index.html', import.meta.url)
            ),
        },
    },
})