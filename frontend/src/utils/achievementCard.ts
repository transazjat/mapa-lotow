import type { AchievementItem } from '../types/achievement'
import { getAchievementBadgeImage } from './achievementBadges'

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error('Nie udało się wczytać grafiki odznaki.'))
    image.src = src
  })
}

function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

function formatContinentLabel(value: number): string {
  const mod10 = value % 10
  const mod100 = value % 100

  const suffix = value === 1
    ? 'kontynent'
    : (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
      ? 'kontynenty'
      : 'kontynentów'

  return `${new Intl.NumberFormat('pl-PL').format(value)} ${suffix}`
}

export async function downloadAchievementCard(
  achievement: AchievementItem,
  completedValue: number,
  nick: string,
  format: 'png' | 'jpg',
): Promise<void> {
  const badgeSrc = getAchievementBadgeImage(
    achievement.family,
    achievement.threshold,
  )

  if (!badgeSrc) {
    throw new Error('Brak grafiki tej odznaki.')
  }

  const badge = await loadImage(badgeSrc)
  const canvas = document.createElement('canvas')
  canvas.width = 1080
  canvas.height = 1080

  const ctx = canvas.getContext('2d')
  if (!ctx) {
    throw new Error('Przeglądarka nie obsługuje generowania karty.')
  }

  const isDistance = achievement.family === 'distance'
  const isAirports = achievement.family === 'airports'
  const isCountries = achievement.family === 'countries'
  const isContinents = achievement.family === 'continents'
  const isAirlines = achievement.family === 'airlines'
  const isAircraft = achievement.family === 'aircraft'
  const formatter = new Intl.NumberFormat('pl-PL')

  const background = ctx.createLinearGradient(0, 0, 1080, 1080)
  background.addColorStop(0, '#061f42')
  background.addColorStop(0.55, '#0b3b70')
  background.addColorStop(1, '#0b274f')
  ctx.fillStyle = background
  ctx.fillRect(0, 0, 1080, 1080)

  ctx.globalAlpha = 0.12
  ctx.strokeStyle = '#ffffff'
  ctx.lineWidth = 2
  for (let x = -200; x < 1280; x += 90) {
    ctx.beginPath()
    ctx.arc(540, 540, x + 350, 0, Math.PI * 2)
    ctx.stroke()
  }
  ctx.globalAlpha = 1

  ctx.fillStyle = '#ffffff'
  ctx.textAlign = 'center'
  ctx.font = '700 32px Arial, sans-serif'
  ctx.fillText('MAPA LOTÓW', 540, 72)

  ctx.fillStyle = '#a9c2dc'
  ctx.font = '500 20px Arial, sans-serif'
  ctx.fillText('ODZNAKA OSIĄGNIĘCIA', 540, 108)

  const badgeSize = 570
  const badgeX = (1080 - badgeSize) / 2
  const badgeY = 160

  ctx.save()
  ctx.beginPath()
  ctx.arc(540, badgeY + badgeSize / 2, badgeSize / 2, 0, Math.PI * 2)
  ctx.clip()
  ctx.drawImage(badge, badgeX, badgeY, badgeSize, badgeSize)
  ctx.restore()

  const primaryTitle = isDistance
    ? `${formatter.format(achievement.threshold)} KM`
    : isAirports
      ? `${formatter.format(achievement.threshold)} LOTNISK`
      : isCountries
        ? `${formatter.format(achievement.threshold)} PAŃSTW`
        : isContinents
          ? formatContinentLabel(achievement.threshold).toUpperCase()
          : isAirlines
            ? `${formatter.format(achievement.threshold)} LINII LOTNICZYCH`
            : isAircraft
              ? `${formatter.format(achievement.threshold)} TYPÓW SAMOLOTÓW`
              : `${achievement.threshold} LOTÓW`

  ctx.fillStyle = '#ffffff'
  ctx.font = '800 58px Arial, sans-serif'
  ctx.fillText(primaryTitle, 540, 800)

  const secondaryTitle = isDistance
    ? 'Kolejny podróżniczy kamień milowy'
    : isAirports
      ? 'Kolejny punkt na Twojej mapie podróży'
      : isCountries
        ? 'Kolejny kraj na Twojej mapie świata'
        : isContinents
          ? 'Kolejny kontynent na Twojej mapie świata'
          : isAirlines
            ? 'Kolejny przewoźnik w Twojej kolekcji'
            : isAircraft
              ? 'Kolejny typ samolotu w Twojej kolekcji'
              : 'Kolejny lotniczy kamień milowy'

  ctx.fillStyle = '#c9d8e6'
  ctx.font = '400 25px Arial, sans-serif'
  ctx.fillText(secondaryTitle, 540, 846)

  ctx.fillStyle = '#ffffff'
  ctx.font = '600 24px Arial, sans-serif'
  ctx.fillText(nick || 'Podróżnik Mapy Lotów', 540, 910)

  const statLine = isDistance
    ? `${formatter.format(completedValue)} km w powietrzu`
    : isAirports
      ? `${formatter.format(completedValue)} różnych lotnisk na mapie`
      : isCountries
        ? `${formatter.format(completedValue)} odwiedzonych państw`
        : isContinents
          ? `${formatContinentLabel(completedValue)} na Twojej mapie podróży`
          : isAirlines
            ? `${formatter.format(completedValue)} różnych linii lotniczych`
            : isAircraft
              ? `${formatter.format(completedValue)} różnych typów samolotów`
              : `${formatter.format(completedValue)} odbytych lotów w historii`

  ctx.fillStyle = '#a9c2dc'
  ctx.font = '400 20px Arial, sans-serif'
  ctx.fillText(statLine, 540, 948)

  ctx.strokeStyle = 'rgba(255,255,255,.22)'
  ctx.beginPath()
  ctx.moveTo(210, 984)
  ctx.lineTo(870, 984)
  ctx.stroke()

  ctx.fillStyle = '#d5e1ec'
  ctx.font = '400 16px Arial, sans-serif'
  ctx.fillText('mapalotow.pl', 540, 1024)

  const mime = format === 'png' ? 'image/png' : 'image/jpeg'
  const quality = format === 'png' ? undefined : 0.94

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (result) => {
        if (result) resolve(result)
        else reject(new Error('Nie udało się wygenerować karty.'))
      },
      mime,
      quality,
    )
  })

  const filename = isDistance
    ? `mapa-lotow-${achievement.threshold}-km.${format}`
    : isAirports
      ? `mapa-lotow-${achievement.threshold}-lotnisk.${format}`
      : isCountries
        ? `mapa-lotow-${achievement.threshold}-panstw.${format}`
        : isContinents
          ? `mapa-lotow-${achievement.threshold}-kontynentow.${format}`
          : isAirlines
            ? `mapa-lotow-${achievement.threshold}-linii-lotniczych.${format}`
            : isAircraft
              ? `mapa-lotow-${achievement.threshold}-typow-samolotow.${format}`
              : `mapa-lotow-${achievement.threshold}-lotow.${format}`

  downloadBlob(blob, filename)
}
