package com.bragado.tvok

import android.net.Uri
import androidx.media3.common.AudioAttributes
import androidx.media3.common.C
import androidx.media3.common.MediaItem
import androidx.media3.common.MediaMetadata
import androidx.media3.common.util.UnstableApi
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.session.MediaLibraryService
import androidx.media3.session.MediaLibraryService.LibraryParams
import androidx.media3.session.MediaLibraryService.MediaLibrarySession
import androidx.media3.session.LibraryResult
import androidx.media3.session.MediaSession
import com.google.common.collect.ImmutableList
import com.google.common.util.concurrent.Futures
import com.google.common.util.concurrent.ListenableFuture
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Callable
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit

private const val ROOT_ID = "bragado-tv-root"
private const val LIVE_ID = "bragado-tv-en-vivo"
// Remote config: line 1 = logo URL, line 2 = stream URL (same as services/remoteConfig.js).
private const val CONFIG_URL = "https://tvbragado.com.ar/admin/config.txt"
// Used only when the remote config cannot be read.
private const val FALLBACK_LOGO_URL = "https://tvbragado.com.ar/assets/bragadologo.png"
private const val FALLBACK_STREAM_URL = "https://vivo.solumedia.com:19360/bragadato/bragadato.m3u8"

private data class RemoteConfig(val logoUrl: String?, val streamUrl: String)

/**
 * Android Auto media app service for Bragado TV.
 *
 * Plays the live HLS stream as AUDIO-ONLY so the app can be offered as a media
 * (radio) app in Android Auto. There is deliberately no video surface here:
 * Android Auto media apps are audio-only.
 *
 * The logo and stream URL are resolved from the remote config on every service
 * start, so changing them server-side requires no rebuild or app update.
 */
class BragadoTvMediaService : MediaLibraryService() {

  private var mediaLibrarySession: MediaLibrarySession? = null

  override fun onCreate() {
    super.onCreate()

    // Resolve the current logo + stream URL before building the session so the
    // browse tree always serves live values (bounded wait; falls back on failure).
    val config = resolveConfig()

    val player = ExoPlayer.Builder(this).build()
    player.setAudioAttributes(
      AudioAttributes.Builder()
        .setUsage(C.USAGE_MEDIA)
        .setContentType(C.AUDIO_CONTENT_TYPE_MUSIC)
        .build(),
      /* handleAudioFocus= */ true
    )
    // Audio-only: never select the video track of the live HLS stream.
    player.trackSelectionParameters =
      player.trackSelectionParameters
        .buildUpon()
        .setTrackTypeDisabled(C.TRACK_TYPE_VIDEO, /* disabled= */ true)
        .build()
    player.playWhenReady = true

    mediaLibrarySession =
      MediaLibrarySession.Builder(
        this,
        player,
        BragadoTvCallback(config.logoUrl, config.streamUrl)
      ).build()
  }

  override fun onGetSession(controllerInfo: MediaSession.ControllerInfo): MediaLibrarySession? =
    mediaLibrarySession

  override fun onDestroy() {
    mediaLibrarySession?.run {
      player.release()
      release()
      mediaLibrarySession = null
    }
    super.onDestroy()
  }

  private fun resolveConfig(): RemoteConfig {
    val executor = Executors.newSingleThreadExecutor()
    return try {
      executor.submit(Callable { fetchRemoteConfig() })
        .get(3, TimeUnit.SECONDS)
    } catch (e: Exception) {
      RemoteConfig(FALLBACK_LOGO_URL, FALLBACK_STREAM_URL)
    } finally {
      executor.shutdownNow()
    }
  }

  private fun fetchRemoteConfig(): RemoteConfig {
    val conn = URL(CONFIG_URL).openConnection() as HttpURLConnection
    conn.connectTimeout = 3000
    conn.readTimeout = 3000
    return try {
      conn.inputStream.bufferedReader(Charsets.UTF_8).use { reader ->
        val lines = reader.readLines()
        val logo = lines.getOrNull(0)?.trim()?.takeIf { it.startsWith("http") }
        val stream = lines.getOrNull(1)?.trim()?.takeIf { it.startsWith("http") }
        if (stream != null) RemoteConfig(logo, stream)
        else RemoteConfig(FALLBACK_LOGO_URL, FALLBACK_STREAM_URL)
      }
    } finally {
      conn.disconnect()
    }
  }

  private class BragadoTvCallback(
    private val logoUrl: String?,
    private val streamUrl: String
  ) : MediaLibrarySession.Callback {

    override fun onGetLibraryRoot(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      params: LibraryParams?
    ): ListenableFuture<LibraryResult<MediaItem>> {
      val rootMetadata =
        MediaMetadata.Builder()
          .setTitle("Bragado TV")
          .setIsBrowsable(true)
          .setIsPlayable(false)
      if (logoUrl != null) {
        rootMetadata.setArtworkUri(Uri.parse(logoUrl))
      }
      val root =
        MediaItem.Builder()
          .setMediaId(ROOT_ID)
          .setMediaMetadata(rootMetadata.build())
          .build()
      return Futures.immediateFuture(LibraryResult.ofItem(root, params))
    }

    override fun onGetChildren(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      parentId: String,
      page: Int,
      pageSize: Int,
      params: LibraryParams?
    ): ListenableFuture<LibraryResult<ImmutableList<MediaItem>>> {
      val children: ImmutableList<MediaItem> =
        if (parentId == ROOT_ID) ImmutableList.of(liveItem())
        else ImmutableList.of()
      return Futures.immediateFuture(LibraryResult.ofItemList(children, params))
    }

    @OptIn(UnstableApi::class)
    override fun onGetItem(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      mediaId: String
    ): ListenableFuture<LibraryResult<MediaItem>> {
      // The car resolves the tapped selection by id; we expose a single live
      // stream, so return it (with its URI) for any requested id.
      return Futures.immediateFuture(LibraryResult.ofItem(liveItem(), null))
    }

    @OptIn(UnstableApi::class)
    override fun onSetMediaItems(
      mediaSession: MediaSession,
      browser: MediaSession.ControllerInfo,
      mediaItems: List<MediaItem>,
      startIndex: Int,
      startPositionMs: Long
    ): ListenableFuture<MediaSession.MediaItemsWithStartPosition> {
      // Single live channel: always return our stream with its URI. The controller
      // may strip the URI and send only the mediaId, so we resolve it here.
      return Futures.immediateFuture(
        MediaSession.MediaItemsWithStartPosition(listOf(liveItem()), 0, 0L)
      )
    }

    override fun onAddMediaItems(
      mediaSession: MediaSession,
      controller: MediaSession.ControllerInfo,
      mediaItems: List<MediaItem>
    ): ListenableFuture<List<MediaItem>> {
      // Ensure added items carry our stream URI.
      return Futures.immediateFuture(listOf(liveItem()))
    }

    private fun liveItem(): MediaItem {
      val metadata =
        MediaMetadata.Builder()
          .setTitle("En vivo")
          .setArtist("Bragado TV")
          .setIsBrowsable(false)
          .setIsPlayable(true)
      if (logoUrl != null) {
        metadata.setArtworkUri(Uri.parse(logoUrl))
      }
      return MediaItem.Builder()
        .setMediaId(LIVE_ID)
        .setUri(streamUrl)
        .setMediaMetadata(metadata.build())
        .build()
    }
  }
}
