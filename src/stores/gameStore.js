
import { defineStore } from 'pinia'
import { ref } from 'vue'
import gamesData from '../data/games.json'

export const useGameStore = defineStore('game', () => {
    const games = ref(gamesData)

    function getGameById(id) {
        return games.value.find(game => game.id === Number(id))
    }

    function searchGames(keyword) {
        const searchText = keyword.trim().toLowerCase()

        if (!searchText) {
            return games.value
        }

        return games.value.filter(game => {
            return (
                game.title.toLowerCase().includes(searchText) ||
                game.titleZh.toLowerCase().includes(searchText)
            )
        })
    }


    function getGamesByGenre(genre) {
        if (!genre) {
            return games.value
        }

        return games.value.filter(game => {
            return game.genre.includes(genre)
        })
    }

    function getNewGames() {
        return games.value.filter(game => {
            return game.status.includes('new')
        })
    }

    function getBestsellers() {
        return games.value.filter(game => {
            return game.status.includes('bestseller')
        })
    }

    function getFeaturedGames() {
        return games.value.filter(game => {
            return game.status.includes('featured')
        })
    }

    return {
        games,
        getGameById,
        searchGames,
        getGamesByGenre,
        getNewGames,
        getBestsellers,
        getFeaturedGames
    }
})
